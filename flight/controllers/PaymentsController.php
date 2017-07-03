<?php

class PaymentsController
{

    // Client side payment check
    public static function checkPayment($orderId)
    {
        $orderId = (int)$orderId;

        $result = [
            'status' => false,
        ];

        if (empty($orderId)) {
            Flight::json($result);
        }

        $billingTable = new BillingTable();
        $userTable = new UsersTable();

        $order = $billingTable->getByOrderId($orderId);

        if (!$order) {
            Flight::json($result);
        } else {
            /** @var $order BillingModel */

            $user = $userTable->fetchOne($order->user_id);

            if ($order->status == 'charged') {
                $userTable = new UsersTable();
                $user = $userTable->fetchOne($order->user_id);
                if ($user) {
                    /** @var $user UsersModel */
                    $result = [
                        'status' => true,
                        'balance' => $user->balance,
                    ];
                }
            }

            $vkApi = new VkApi(
                Flight::config()->system['appId'],
                Flight::config()->system['appSecret']
            );
            $vkApi->asService();

            $orderInfo = false;
            try {
                $orderInfo = $vkApi->secureOrdersGetById($order->order_id, boolval($order->test));
            } catch (\Exception $e) {}

            $orderInfo = !(empty($orderInfo[0])) ? $orderInfo[0] : false;

            if (!$orderInfo) {
                Flight::json($result);
            }

            if ($order->item_price != $orderInfo['amount']) {
                $order->item_price = $orderInfo['amount'];
                $billingTable->save($order, $order->id);
            }

            if ($order->status != $orderInfo['status']) {
                $order->status = $orderInfo['status'];

                $items = Flight::config()->game['gameShopItems'];

                // Статус обновился на "Оплачен", добавляем бабки
                if ($order->status == 'charged') {
                    $user->balance += (int)$items[$orderInfo['item']]['coins'];
                    $userTable->save($user, $user->id);
                }

                // Статус обновился на "Возвращен", забираем бабло обратно :D
                if ($order->status == 'refunded') {
                    $user->balance -= (int)$items[$orderInfo['item']]['coins'];
                    $userTable->save($user, $user->id);
                }

                $billingTable->save($order, $order->id);
            }

            if ($order->status == 'charged') {
                $userTable = new UsersTable();
                $user = $userTable->fetchOne($order->user_id);
                if ($user) {
                    /** @var $user UsersModel */
                    $result = [
                        'status' => true,
                        'balance' => $user->balance,
                    ];
                }
            }
        }

        Flight::json($result);
    }

    // Server side payment callback - called by VK
    public static function vkCallback()
    {
        $post = Flight::request()->data->getData();

        $signature = $post['sig'];
        unset($post['sig']);
        ksort($post, SORT_STRING);

        $validationString = '';
        foreach ($post as $key => $val) {
            $validationString .= $key . '=' . $val;
        }

        $secret = Flight::config()->system['appSecret'];

        if ($signature != md5($validationString . $secret)) {
            Flight::json([
                'error' => [
                    'error_code' => 10,
                    'error_msg'  => 'Несовпадение вычисленной и переданной подписи запроса.',
                    'critical'   => true,
                ],
            ]);
        }

        $usersTable = new UsersTable();
        $user = $usersTable->fetchOne((int)$post['user_id']);
        /** @var $user UsersModel|false */
        if (!$user) {
            Flight::json([
                'error' => [
                    'error_code' => 22,
                    'error_msg'  => 'Пользователя не существует.',
                    'critical'   => true,
                ],
            ]);
        }

        $items = Flight::config()->game['gameShopItems'];

        switch ($post['notification_type']) {
            case 'get_item':
            case 'get_item_test':
                $item  = !empty($items[$post['item']])
                    ? $items[$post['item']]
                    : false;

                if ($item) {
                    unset($item['postfix'], $item['coins']);
                    Flight::json(['response' => $item]);
                } else {
                    Flight::json([
                        'error' => [
                            'error_code' => 20,
                            'error_msg' => 'Товара не существует.',
                            'critical' => true,
                        ]
                    ]);
                }
                break;

            case 'order_status_change':
            case 'order_status_change_test':
                if (in_array($post['status'], ['chargeable', 'declined', 'cancelled', 'charged', 'refunded'])) {
                    $orderId = (int)$post['order_id'];
                    $billingTable = new BillingTable();

                    $order = $billingTable->getByOrderId($orderId);
                    if (!$order) {
                        $order = new BillingModel();
                        $order->order_id = $orderId;
                        $order->user_id  = (int)$post['user_id'];
                        $order->item_id  = $post['item_id'];
                        $order->date     = (int)$post['date'];
                        $order->status   = $post['status'];
                        $order->item_price = $post['item_price'];
                        $order->test     = ($post['notification_type'] == 'order_status_change_test') ? 1 : 0;

                        $order->id = $billingTable->save($order);
                        $order = $billingTable->getByOrderId($orderId);
                    }
                    if ($order->status != $post['status']) {
                        $order->status = $post['status'];
                        $billingTable->save($order, $order->id);

                        // Статус обновился на "Оплачен", добавляем бабки
                        if ($order->status == 'charged') {
                            $user->balance += (int)$items[$post['item_id']]['coins'];
                            $usersTable->save($user, $user->id);
                        }

                        // Статус обновился на "Возвращен", забираем бабло обратно :D
                        if ($order->status == 'refunded') {
                            $user->balance -= (int)$items[$post['item_id']]['coins'];
                            $usersTable->save($user, $user->id);
                        }
                    }

                    Flight::json([
                        'response' => [
                            'order_id'     => $orderId,
                            'app_order_id' => $order->id,
                        ]
                    ]);
                } else {
                    Flight::json([
                        'error' => [
                            'error_code' => 100,
                            'error_msg' => 'Передано непонятно что вместо chargeable.',
                            'critical' => true
                        ]
                    ]);
                }
                break;

            default:
                Flight::json([
                    'error' => [
                        'error_code' => 1,
                        'error_msg'  => 'Передан не верный тип оповещения(' . $post['notification_type'] . ').',
                        'critical'   => true
                    ]
                ]);
        }
    }
}