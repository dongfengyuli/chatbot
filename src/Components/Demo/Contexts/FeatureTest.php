<?php


namespace Commune\Components\Demo\Contexts;


use Commune\Chatbot\App\Callables\Actions\Redirector;
use Commune\Chatbot\App\Callables\Actions\Talker;
use Commune\Chatbot\App\Callables\StageComponents\AskContinue;
use Commune\Chatbot\App\Callables\StageComponents\Menu;
use Commune\Chatbot\App\Contexts\TaskDef;
use Commune\Chatbot\App\Messages\Replies\Link;
use Commune\Chatbot\Blueprint\Message\Message;
use Commune\Chatbot\OOHost\Context\Depending;
use Commune\Chatbot\OOHost\Context\Exiting;
use Commune\Chatbot\OOHost\Context\Stage;
use Commune\Chatbot\OOHost\Dialogue\Dialog;
use Commune\Chatbot\OOHost\Dialogue\Hearing;
use Commune\Chatbot\OOHost\Directing\Navigator;
use Commune\Chatbot\OOHost\Session\Session;
use Commune\Components\Demo\Cases\Maze\MazeInt;
use Commune\Components\Predefined\Intents\Dialogue\OrdinalInt;
use Commune\Components\Demo\Contexts\Features\SubDialogCase;
use Commune\Components\Demo\Contexts\Testing\GcTask;
use Commune\Components\Demo\Memories\Sandbox;

class FeatureTest extends TaskDef
{
    const DESCRIPTION = 'demo.contexts.featureTest';

    public static function __depend(Depending $depending): void
    {
    }

    public function __exiting(Exiting $listener): void
    {
        $listener
            ->onCancel(Talker::say()->info('cancel from '. __METHOD__))
            ->onQuit(Talker::say()->info('quit from '. __METHOD__));
    }


    public function __hearing(Hearing $hearing) : void
    {
        $hearing
            ->is('#q', Redirector::goFulfill())
            ->is('#r', Redirector::goRestart());
    }

    public function __onStart(Stage $stage): Navigator
    {
        return $stage->component(
            new Menu(
                '请选择功能测试用例 (输入 #q 退出测试, #r 回到选项)',
                [
                    '常用匹配逻辑' => 'testMatch',
                    '段落测试' => 'testParagraph',
                    '上下文记忆' => 'testMemory',
                    'confirmation && emotion' => 'testConfirmation',
                    '语境嵌套' => 'testSubDialog',
                    'askContinue 机制' => 'testAskContinue',
                    'gc 机制' => 'testGc',
                    'stage exiting 事件' => 'testExiting',
                ]
            )
        );
    }

    /**
     * 测试匹配逻辑.
     * @param Stage $stage
     * @return Navigator
     */
    public function __onTestMatch(Stage $stage): Navigator
    {

        return $stage->buildTalk()
            ->info(
                <<<EOF
可测试的内容:
-   /^hello/ 正则匹配.
-   /^test$/ 正则匹配并尝试经过管道.
-   /depend/ 查看闭包的 dependencies 参数
-   [测试, [关键字,keyword]] 关键字匹配
-   ordinalInt 正则匹配.
EOF
            )
            ->hearing()

            ->todo(function(Dialog $dialog, Message $message) : Navigator {
                $dialog->say()->info('hello.world', ['input' => $message->getText()]);
                return $dialog->repeat();

            })
            ->pregMatch('/^hello/', [])

            ->todo(function(Dialog $dialog) {
                $dialog->say()->info('go to testPipeStart stage');
                return $dialog->goStagePipes(['testPipeStart', 'testMatch']);
            })
            ->pregMatch('/^test$/')

            ->otherwise()

            // 适用 第n个 这种形式, 匹配
            ->isIntent(OrdinalInt::class, function(OrdinalInt $int, Dialog $dialog){
                $dialog->say()->info('匹配到了%ord%', [
                    'ord' => implode(',', $int->ordinal)
                ]);

                return $dialog->repeat();

            })

            ->hasKeywords(
                [
                    '测试', ['关键字', 'keyword']
                ],
                function (Dialog $dialog) {
                    $dialog->say()->info('命中测试关键字');
                    return $dialog->repeat();
                }
            )

            ->todo(function(Dialog $dialog, array $dependencies){

                    $talk = $dialog->say();
                    $talk = $talk->beginParagraph();
                    $talk->info('dependencies are :');
                    foreach ($dependencies as $key => $type) {
                        $talk->info("$key : $type");
                    }
                    $talk->endParagraph();
                    return $dialog->repeat();
            })
                ->pregMatch('/depend/')

            ->end();

    }



    /**
     * 情绪功能的测试. 目前测试 intent => emotion => confirmation
     * @param Stage $stage
     * @return Navigator
     */
    public function __onTestConfirmation(Stage $stage): Navigator
    {
        $result = $stage->buildTalk();

        $result = $result
            ->askConfirm('try to confirm this. test positive emotion. ')
            ->wait();

        $result = $result->hearing()
            ->isPositive(function(Dialog $dialog){
                $dialog->say()->info('is positive emotion');
                return $dialog->goStage('menu');
            })
            ->isNegative(function(Dialog $dialog){
                $dialog->say()->info('is negative emotion');
                return $dialog->goStage('menu');
            })
            ->end(function(Dialog $dialog){
                $dialog->say()->warning('nether yes nor no');
                return $dialog->goStage('menu');
            });

        return $result;
    }

    /**
     *
     * 测试会话嵌套.
     * 测试子会话的各种功能.
     * 可以测试的点:
     *
     *
     * 1. before : 父dialog 拦截到, 仍然进入子dialog
     * 2. stop  : 父dialog 拦截到, 不进入子dialog
     * 3. miss : 子dialog miss, 父dialog 返回拦截到
     * 4. quit : 子dialog quit, 父dialog 拦截到, 返回菜单.
     * 5. fulfill : 子dialog fulfill, 触发 quit
     * 6. next : 子dialog 切换stage, 直到触发 fulfill
     * 7. maze : 子dialog 进入迷宫游戏
     * 8. stage : 查看子dialog 的stage
     *
     * @param Stage $stage
     * @return Navigator
     */
    public function __onTestSubDialog(Stage $stage): Navigator
    {
        return $stage
            // 正常的stage事件.
            ->onStart(function(Dialog $dialog){
                $dialog->say()->info('sub dialog start');
            })
            ->onCallback(function(Dialog $dialog){
                $dialog->say()->info('sub dialog callback');
            })
            ->onFallback(function(Dialog $dialog){
                $dialog->say()->info('sub dialog fallback');
            })

            // 开启 sub dialog
            ->onSubDialog(
                $this->getId(),
                function(){
                    return new SubDialogCase();
                }
            )
            // 进入子会话之前.
            ->onBefore(function(Dialog $dialog){
                return $dialog->hear()
                    ->is('before', function(Dialog $dialog){
                        $dialog->say()->info('hit before');
                        return null;
                    })
                    ->is('menu', function(Dialog $dialog) {
                        $dialog->say()->info('stop sub dialog and go start');
                        return $dialog->restart();
                    })
                    ->heardOrMiss();
            })
            // 子会话 wait 时
            ->onWait(function(Dialog $dialog){
                $dialog->say()->info('sub dialog is wait');
                return $dialog->wait();
            })
            // 子会话 miss 时
            ->onMiss(function(Dialog $dialog){
                $dialog->say()->info('sub dialog miss match');
                return $dialog->hear()
                    ->is('miss', function(Dialog $dialog){
                        $dialog->say()->info('catch miss');
                        return $dialog->wait();
                    })
                    ->end();
            })

            // 子会话退出时.
            ->onQuit(function(Dialog $dialog){
                $dialog->say()->info('sub dialog want quit');
                return $dialog->restart();
            })
            ->end();
    }


    public function __onTestMemory(Stage $stage): Navigator
    {
        return $stage->buildTalk()
            ->askChoose(
                '测试记忆功能',
                [
                    'a' => 'sandbox : 测试在config里定义的 memory',
                    'b' => 'sandbox class: 测试用类定义的 memory',
                    '返回',
                ]
            )
            ->hearing()

            ->is(0, Redirector::goRestart())

            ->todo(function(Dialog $dialog, Session $session) {
                $test = $session->memory['sandbox']['test'] ?? 0;
                $dialog->say()
                    ->info("test is : %test%", ['test' => $test]);

                $session->memory['sandbox']['test'] = $test + 1;

                return $dialog->repeat();

            })
                ->isChoice('a')
                ->is('sandbox')

                ->otherwise()

            ->isChoice('b', function(Dialog $dialog){

                    $s = Sandbox::from($this);
                    $test = $s->test ?? 0;
                    $test1 = $s->test1 ?? 0;
                    $s->test = $test + 1;
                    $s->test1 = $test1 + 2;

                    $dialog->say()
                        ->withContext($s, ['test', 'test1'])
                        ->info(
                            'class '
                            . Sandbox::class
                            . ' value is test:%test%, test1:%test1%'
                        );

                    return $dialog->repeat();
                })
            ->end();
    }


    /**
     * 通过 stage 管道.
     * @param Stage $stage
     * @return Navigator
     */
    public function __onStagePipeTest(Stage $stage): Navigator
    {
        return $stage->onStart(function(Dialog $dialog) {
            $dialog->say()
                ->info('test stage start')
                ->askVerbal('输入一个值 (会展示这个值然后跳到下一步)');
            return $dialog->wait();

        })->wait(function(Dialog $dialog, Message $message) {

            $dialog->say()->info('您输入的是:'.$message->getText());
            return $dialog->next();
        });

    }


    /**
     * test to do api and test define stage by annotation
     *
     * @stage 用 annotation 方式定义这个 stage
     * @param Stage $stage
     * @return Navigator
     */
    public function testTodo(Stage $stage) : Navigator
    {
        return $stage->buildTalk()
            ->info('测试 todo api. 请输入 123, 456, 789 测试(quit 退出):')
            ->wait()
            ->hearing()

            ->todo(function(Dialog $dialog, Message $message){
                $dialog->say()->info('matched case1 ' . $message->getText());
                return $dialog->wait();
            })
                ->is('123')
                ->is('456')
                ->soundLike('一二三四')
                ->soundLikePart('二三四五')

            ->todo(function(Dialog $dialog){

                $dialog->say()->info('matched case2 789');
                return $dialog->wait();
            })
                ->pregMatch('/^789/')

            ->todo(function(Dialog $dialog){
                return $dialog->goStage('menu');
            })
                ->is('quit')

            ->end();
    }


    public function __onTestParagraph(Stage $stage) : Navigator
    {
        return $stage->buildTalk()

            ->beginParagraph()
                ->info('beginParagraph.')
                ->withReply($link = new Link('https://communechatbot.com'))
                ->error('endParagraph.')
            ->endParagraph()

            ->withReply($link)

            ->action(Redirector::goRestart());
    }


    public function __onTestAskContinue(Stage $stage) : Navigator
    {
        $scripts = [];
        for( $i = 0; $i < 10 ; $i++) {
            $scripts[] = Talker::say()->info("step is $i");
        }
        return $stage->component(
            (new AskContinue($scripts))
                ->onBackward(function(Dialog $dialog) : Navigator {
                    $dialog->say()->info('backward to previous stage');
                    return $dialog->restart();
                })
                ->onFinal(function(Dialog $dialog) : Navigator{
                    $dialog->say()->info('end loop successful');
                    return $dialog->restart();
                })
                ->onHearing(function(Hearing $hearing) : void {
                    $hearing->is(
                        'test',
                        Talker::say()->info('hit hearing component')
                    );
                })
                ->onHearingFallback(
                    Talker::say()->warning('not heard')
                )
                ->onHelp()
        );
    }

    public function __onTestGc(Stage $stage) : Navigator
    {
        return $stage->dependOn(new GcTask($this), Redirector::goRestart());
    }


    public function __onTestExiting(Stage $stage) : Navigator
    {
        return $stage
            ->onExiting(function(Exiting $exiting){
                $exiting
                    ->onCancel(Talker::say()->info('cancel from testExiting Stage'))
                    ->onQuit(Talker::say()->info('quit from testExiting Stage'));
            })
            ->dependOn(MazeInt::class, Redirector::goRestart());
    }

}