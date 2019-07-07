<?php


namespace Commune\Chatbot\OOHost\Directing\Stage;


use Commune\Chatbot\Blueprint\Message\Message;
use Commune\Chatbot\OOHost\Dialogue\Dialog;
use Commune\Chatbot\OOHost\Directing\AbsNavigator;
use Commune\Chatbot\OOHost\Directing\Navigator;
use Commune\Chatbot\OOHost\History\History;

/**
 * 回调context 当前的stage
 * callback value 是 Message, 有三种来源:
 * - wait 用户发来的消息
 * - yield 服务发来的回调消息
 * - dependOn 其它的context 以自身作为参数回调.
 *
 * 如果 callback value 不存在,
 * 则说明是一个fallback 启动.
 * sleep 的context wake 了
 */
class CallbackStage extends AbsNavigator
{
    protected $callbackValue;

    public function __construct(
        Dialog $dialog,
        History $history,
        Message $callbackValue = null
    )
    {
        $this->callbackValue = $callbackValue;
        parent::__construct($dialog, $history);
    }


    public function doDisplay(): ? Navigator
    {
        $context = $this->history->getCurrentContext();
        $stage = $this->history->currentTask()->getStage();

        return $context->getDef()
            ->callbackStage(
                $context,
                $this->dialog,
                $stage,
                $this->callbackValue
            );
    }


}