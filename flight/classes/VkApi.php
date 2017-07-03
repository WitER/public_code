<?php

/**
 * Class VkApi
 * @version  0.0.4
 * @author   Dmitry Slesarenko aka WitER <dimka.witer@gmail.com>
 * @PHP      5.6
 */
class VkApi
{
    public $appId;
    public $appSecret;
    public $accessToken;

    private $serviceMode = false;
    private $serviceToken;

    private $apiBaseUrl = 'https://api.vk.com/method/';
    private $apiVersion = '5.53';


    /**
     * VkApi constructor.
     * @param integer $appId Vk Application Id
     * @param string $appSecret Vk Application Secred
     * @param null|string $accessToken User access token
     */
    public function __construct($appId, $appSecret, $accessToken = null)
    {
        $this->appId        = $appId;
        $this->appSecret    = $appSecret;
        $this->accessToken  = $accessToken;
    }

    public function usersGet($userIds = [], $nameCase = 'nom', $fields = 'photo_100')
    {
        if (!is_array($userIds)) {
            $userIds = [(int)$userIds];
        }
        if (!in_array($nameCase, ['nom', 'gen', 'dat', 'acc', 'ins', 'abl'])) {
            $nameCase = 'nom';
        }

        $requestData = [
            'user_ids'  => implode(',', $userIds),
            'name_case' => $nameCase,
            'fields'    => $fields,
        ];
        if (empty($userIds)) {
            unset($requestData['user_ids']);
        }

        $result = $this->query('users.get', $requestData);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']['error_msg'], $result['error']['error_code']);
        }

        return $result['response'];
    }

    public function wallGetById($id)
    {
        $requestData = [
            'posts' => $id
        ];

        $result = $this->query('wall.getById', $requestData);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']['error_msg'], $result['error']['error_code']);
        }

        return $result['response'];
    }

    public function getAppFriends()
    {
        $result = $this->query('friends.getAppUsers');

        if (!empty($result['error'])) {
            throw new \Exception($result['error']['error_msg'], $result['error']['error_code']);
        }

        return $result['response'];
    }

    public function getAppPermissions($userId)
    {
        $result = $this->query('account.getAppPermissions', ['user_id' => $userId]);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']['error_msg'], $result['error']['error_code']);
        }

        return $result['response'];
    }

    public function isGroupMember($groupId, $userId)
    {
        $result = $this->query('groups.isMember', ['group_id' => $groupId, 'user_id' => $userId]);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']['error_msg'], $result['error']['error_code']);
        }

        return $result['response'];
    }

    public function secureOrdersGetById($id, $test = false)
    {
        if (!$this->serviceMode || empty($this->serviceToken)) {
            return false;
        }

        $requestData = [
            'order_id' => (int)$id,
            'test_mode'=> $test
        ];
        $result = $this->query('orders.getById', $requestData);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']['error_msg'], $result['error']['error_code']);
        }

        return $result['response'];
    }

    public function secureCheckToken($token, $ip = '')
    {
        if (!$this->serviceMode || empty($this->serviceToken)) {
            return false;
        }

        $requestData = [
            'token' => $token,
            'ip'=> $ip
        ];
        $result = $this->query('secure.checkToken', $requestData);
        return empty($result['error']);
    }

    public function secureGetAppBalance()
    {
        if (!$this->serviceMode || empty($this->serviceToken)) {
            return false;
        }
        $result = $this->query('secure.getAppBalance');
        if (!empty($result['error'])) {
            throw new \Exception($result['error']['error_msg'], $result['error']['error_code']);
        }

        return $result['response'];
    }

    public function secureSendNotification($userIds, $message)
    {
        if (!$this->serviceMode || empty($this->serviceToken)) {
            return false;
        }
        $requestData = [
            'user_ids' => is_array($userIds) ? $userIds : [$userIds],
            'message'  => $message
        ];
        $result = $this->query('secure.sendNotification', $requestData);
        if (!empty($result['error'])) {
            throw new \Exception($result['error']['error_msg'], $result['error']['error_code']);
        }

        return $result['response'];
    }

    // BASE FUNCTIONS
    /**
     * Set current access token
     * @param $token
     * @return void
     */
    public function setToken($token)
    {
        $this->accessToken = $token;
    }

    public function asClient()
    {
        $this->serviceMode = false;
        return $this;
    }

    public function asService()
    {
        if (empty($this->serviceToken)) {
            $queryUrl = 'https://oauth.vk.com/access_token?client_id=' . $this->appId . '&client_secret=' . $this->appSecret . '&grant_type=client_credentials';
            $result = $this->query('', false, $queryUrl);
            if (!empty($result['error'])) {
                throw new \Exception($result['error']['error_msg'], $result['error']['error_code']);
            }
            $this->serviceToken = $result['access_token'];
        }

        $this->serviceMode = true;
        return $this;
    }

    /**
     * ApiQuery
     * @param string $method
     * @param array $data
     * @param bool|string $url
     * @return array
     */
    public function query($method, $data = [], $url = false) {
        $queryData = false;
        if ($data !== false) {
            if (empty($data['v'])) {
                $data['v'] = $this->apiVersion;
            }
            if (!empty($this->accessToken)) {
                $data['access_token'] = $this->accessToken;
            }
            if (!empty($this->serviceToken) && $this->serviceMode) {
                $data['access_token'] = $this->serviceToken;
                $data['client_secret'] = $this->appSecret;
            }
            $queryData = http_build_query($data);
        }
        $url = (!$url ? $this->apiBaseUrl . $method : $url) . ($queryData != false ? '?' . $queryData : '');

        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);

        $result = curl_exec( $ch );

        curl_close( $ch );


        $result = $this->isJson($result) ? json_decode($result, true) : $result;
        return $result;
    }

    private function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}