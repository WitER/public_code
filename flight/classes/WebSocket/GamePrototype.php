<?php
namespace app\classes\WebSocket;

class GamePrototype
{

    public $state;
    public $stateTimeout;
    public $normalRounds;
    public $currentRound;
    public $questionsCount;
    public $users;
    public $round;
    public $usedQuestions;
    public $type;

    public function __construct($type)
    {
        $this->state = 'wait';
        $this->currentRound = 0;
        $this->normalRounds = count(\Flight::config()->game['multiPlayerNormalRounds']);
        $this->questionsCount = 0;
        $this->users = [];
        $this->round = [];
        $this->usedQuestions = [];
        $this->stateTimeout = time() + \Flight::config()->game['multiPlayerWaitTime'];
        $this->type = $type;
    }

    public function addUser($connectionId, $userInfo)
    {
        if (count($this->users) >= 2) {
            return false;
        }
        $user = new \stdClass();
        $user->connectionId = $connectionId;
        $user->id = $userInfo['id'];
        $user->name = $userInfo['name'];
        $user->avatar = $userInfo['avatar'];
        $user->record = $userInfo['record'];
        $user->currentQuestion = 0;
        $user->questionsCount = 0;
        $user->questionTimeout = 0;
        $user->validAnswers = 0;
        $user->roundAnswers = [];
        unset($connectionId, $userInfo);
        $this->users[$user->connectionId] = $user;

        if (count($this->users) == 2) {
            return $this->changeState('ready');
        }
        return true;
    }

    // Проверяет текущее состояние
    public function check($connId = false)
    {
        switch ($this->state) {
            case 'wait':
                return time() < $this->stateTimeout ? 'wait' : 'timeout';
                break;
            case 'ready':
                return 'ready';
                break;
            case 'current':
                if (time() > $this->stateTimeout) {
                    if ($this->currentRound < $this->normalRounds) {
                        foreach ($this->users as $connId => $user) {
                            for ($i = 1; $i <= $this->round['questionsCount']; $i++) {
                                if (!isset($this->users[$connId]->roundAnswers[$i]) || !in_array($this->users[$connId]->roundAnswers[$i], [0, 1])) {
                                    $this->users[$connId]->roundAnswers[$i] = 0;
                                }
                            }
                        }
                        $this->buildRound();
                    }
                    //$this->changeState('timeout');
                    //return 'timeout';
                }
                if ($this->playersTimeout()) {
                    $this->changeState('timeout');
                    return 'timeout';
                }
                if  ($this->checkRoundComplete($connId) && !$this->checkRoundComplete()) {
                    return 'waitForEnemyComplete';
                }
                if  ($this->checkRoundComplete() && !$this->checkGameComplete()) {
                    return 'roundComplete';
                }
                if ($this->checkGameComplete()) {
                    $this->changeState('complete');
                    return 'complete';
                }
                return 'current';
                break;
            default:
                return $this->state;
        }
    }

    // Запускаем игру
    public function start()
    {
        $this->changeState('current');
        foreach ($this->users as $id => $user) {
            //$this->users[$id]->currentQuestion++;
            $this->users[$id]->questionTimeout = $this->round['timeout'] + time();
        }
    }

    public function playerOut($connectionId)
    {
        $this->changeState('complete');
        $this->users[$connectionId]->validAnswers = -1;
    }

    // Создаем следующий раунд
    public function nextRound()
    {
        return $this->buildRound();
    }

    // Получаем победителя \ проигравшего
    public function getWinner($looser = false)
    {
        if (count($this->users) < 2) {
            reset($this->users);
            return current($this->users);
        }

        reset($this->users);
        $playerOne = current($this->users);
        next($this->users);
        $playerTwo = current($this->users);

        if ($playerOne->validAnswers > $playerTwo->validAnswers) {
            return $looser == false ? $playerOne : $playerTwo;
        }
        if ($playerOne->validAnswers < $playerTwo->validAnswers) {
            return $looser ? $playerOne : $playerTwo;
        }
        return false;
    }

    // Сохраняем ответ
    public function answer($connectionId, $answer) {
        if ($this->state != 'current') {
            return false;
        }
        $currentQuestion = $this->users[$connectionId]->currentQuestion;
        $validAnswer = $answer == $this->round['questions'][$currentQuestion]->valid_answer;
        $this->users[$connectionId]->roundAnswers[$currentQuestion] = $validAnswer ? 1 : 0;
        if ($validAnswer) {
            $this->users[$connectionId]->validAnswers++;
        }
        $this->users[$connectionId]->questionTimeout = time() + $this->round['timeout'];
        if ($this->users[$connectionId]->currentQuestion < $this->round['questionsCount']) {
            $this->users[$connectionId]->currentQuestion++;
        }
        $this->users[$connectionId]->questionsCount = $this->questionsCount;
        return $validAnswer;
    }

    public function getGameState($connectionId) {
        $user = $this->users[$connectionId];
        $enemyConnId = array_diff(array_keys($this->users), [$user->connectionId]);
        reset($enemyConnId);
        $enemy = $this->users[current($enemyConnId)];
        return [
            'roundTimeout'  => $this->stateTimeout - time() + 2,
            'timeOut'  => $this->users[$connectionId]->questionTimeout - time(),
            'question' => $this->round['questions'][$user->currentQuestion]->getForGame(),
            'round'    => $this->currentRound,
            'user'     => $user,
            'enemy'    => $enemy,
            'questionsInRound' => $this->round['questionsCount'],
            'questionsCount' => $this->questionsCount,
            'gameType' => $this->type,
        ];

    }

    private function playersTimeout()
    {
        foreach ($this->users as $connectionId => $user) {
            if ($user->questionTimeout < time()) {
                if (!isset($this->users[$connectionId]->roundAnswers[$user->currentQuestion])) {
                    $this->users[$connectionId]->roundAnswers[$user->currentQuestion] = 0;
                }
                $this->users[$connectionId]->questionTimeout = time() + $this->round['timeout'];
                if ($this->users[$connectionId]->currentQuestion < $this->round['questionsCount']) {
                    $this->users[$connectionId]->currentQuestion++;
                }
                $this->users[$connectionId]->questionsCount = $this->questionsCount;
                //return true;
            }
        }
        return false;
    }

    // Проверяем конец игры
    private function checkGameComplete()
    {
        reset($this->users);
        $playerOne = current($this->users);
        next($this->users);
        $playerTwo = current($this->users);

        // Ответы даны на все вопросы раунда обоими игроками
        if ($this->checkRoundComplete()) {
            // Если раунд последний или бонусный
            if ($this->currentRound >= $this->normalRounds) {
                // Есть победитель
                return $playerOne->validAnswers != $playerTwo->validAnswers;
            }
            // Раунд не последний и не бонусный - победителей быть не может
            return false;
        }
        // Если один, или оба из игроков не ответили на все вопросы игра не закончена
        return false;
    }

    // Проверяем конец раунда
    private function checkRoundComplete($connId = false)
    {
        if ($connId) {
            return (count($this->users[$connId]->roundAnswers) == $this->round['questionsCount']);
        }
        reset($this->users);
        $playerOne = current($this->users);
        next($this->users);
        $playerTwo = current($this->users);

        // Ответы даны на все вопросы раунда обоими игроками - раунд окончен.
        return (
            (count($playerOne->roundAnswers) == $this->round['questionsCount']) &&
            (count($playerTwo->roundAnswers) == $this->round['questionsCount'])
        );

    }

    // Служебная функция
    private function changeState($newState)
    {
        switch ($newState) {
            case 'wait':
                $this->__construct();
                break;
            case 'ready':
                if ($this->buildRound()) {
                    $this->state = 'ready';
                    $this->stateTimeout = time() + \Flight::config()->game['multiPlayerWaitTime'] + 2;
                } else {
                    $this->changeState('error');
                }
                break;
            case 'error':
                $this->state = 'error';
                break;
            case 'current':
                $this->state = 'current';
                $this->stateTimeout = time() + ($this->round['questionsCount'] * $this->round['timeout']) + 2;
                break;
            case 'complete':
                $this->state = 'complete';
                $this->stateTimeout = 0;
                break;
            case 'timeout':
                $this->state = 'timeout';
                $this->stateTimeout = 0;
                break;
        }
    }

    // Создаем раунд
    private function buildRound()
    {
        $this->currentRound++;
        $this->round = ($this->currentRound <= $this->normalRounds)
            ? \Flight::config()->game['multiPlayerNormalRounds'][$this->currentRound]
            : \Flight::config()->game['multiPlayerBonusRound'];
        $this->stateTimeout = time() + ($this->round['questionsCount'] * $this->round['timeout']) + 2;
        return $this->loadQuestions() ? true : false;
    }

    // Подгружаем вопросы
    private function loadQuestions()
    {
        $collection = new \OneVsOneQuestionsTable();

        $this->round['questions'] = [];
        $questions = $collection->getQuestion($this->round['questionsCount'], $this->usedQuestions);
        if (!empty($questions)) {
            foreach ($questions as $k => $question) {
                $this->round['questions'][$k + 1] = $question;
                $this->questionsCount++;
                $this->usedQuestions[] = $question->id;
            }

            if (!empty($this->users)) {
                foreach ($this->users as $connId => $user) {
                    $this->users[$connId]->roundAnswers = [];
                    $this->users[$connId]->currentQuestion = 1;
                    $this->users[$connId]->questionTimeout = $this->round['timeout'] + time();
                    $this->users[$connId]->questionsCount = $this->questionsCount;
                }
            }
            return true;
        }
        return false;
    }



}