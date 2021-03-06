<?php


namespace Commune\Components\Demo\Contexts;


use Commune\Chatbot\App\Callables\Actions\Talker;
use Commune\Chatbot\App\Callables\StageComponents\Menu;
use Commune\Chatbot\App\Contexts\TaskDef;
use Commune\Chatbot\OOHost\Context\Depending;
use Commune\Chatbot\OOHost\Context\Exiting;
use Commune\Chatbot\OOHost\Context\Stage;
use Commune\Chatbot\OOHost\Dialogue\Dialog;
use Commune\Chatbot\OOHost\Dialogue\Hearing;
use Commune\Chatbot\OOHost\Directing\Navigator;

class DemoHome extends TaskDef
{
    const DESCRIPTION = "demo的入口";

    public function __onStart(Stage $stage): Navigator
    {
        $menu = new Menu(
            '请您选择: ',
            [
                FeatureTest::class,
                WelcomeUser::class,
                DevTools::class,
            ]
        );

        return $stage->buildTalk()
            ->info('欢迎来到 CommuneChatbot Demo 测试入口.')
            ->toStage()
            ->onFallback(Talker::say()->info('完成测试'))
            ->component($menu);
    }

    public static function __depend(Depending $depending): void
    {
    }

    /**
     * 注册全局的闲聊.
     * @param Hearing $hearing
     */
    public function __hearing(Hearing $hearing) : void
    {
        $hearing->runAnyIntent();
    }


    public function __exiting(Exiting $listener): void
    {
        $listener
            ->onQuit(function(Dialog $dialog){
                $dialog->say()->info('quit from quiting event');
                return null;
            })
            ->onCancel(function(Dialog $dialog) {
                $dialog->say()->info('quit from cancel event');
                return $dialog->quit();
            });
    }


}