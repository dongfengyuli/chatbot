<?php


namespace Commune\Components\UnheardLike\Contexts;


use Commune\Chatbot\App\Callables\Actions\Redirector;
use Commune\Chatbot\App\Callables\Actions\Talker;
use Commune\Chatbot\App\Callables\StageComponents\AskContinue;
use Commune\Chatbot\App\Contexts\TaskDef;
use Commune\Chatbot\App\Messages\QA\Choice;
use Commune\Chatbot\Blueprint\Message\VerbalMsg;
use Commune\Chatbot\Framework\Exceptions\ChatbotLogicException;
use Commune\Chatbot\OOHost\Context\Definition;
use Commune\Chatbot\OOHost\Context\Depending;
use Commune\Chatbot\OOHost\Context\Exiting;
use Commune\Chatbot\OOHost\Context\Stage;
use Commune\Chatbot\OOHost\Dialogue\Dialog;
use Commune\Chatbot\OOHost\Dialogue\Hearing;
use Commune\Chatbot\OOHost\Directing\Navigator;
use Commune\Components\Predefined\Intents\Loop\BreakInt;
use Commune\Components\Predefined\Intents\Loop\RewindInt;
use Commune\Components\Predefined\Intents\Navigation\BackwardInt;
use Commune\Components\UnheardLike\Contexts\Memories\EpisodeMem;
use Commune\Components\UnheardLike\Libraries\EpisodeDefinition;
use Commune\Components\UnheardLike\Libraries\UnheardRegistrar;
use Commune\Components\UnheardLike\Options\Episode;
use Commune\Components\UnheardLike\Options\Frame;
use Commune\Container\ContainerContract;

/**
 
 * @property-read Episode $episode
 * @property-read EpisodeMem $mem
 *
 * @property string|null $isMarking
 * @property int|null $isAnswering
 */
class EpisodeTask extends TaskDef
{

    const DESCRIPTION = '疑案追声 模式对话游戏的实现demo';

    // 标记通过 config 生成.
    const CONTEXT_TAGS = [
        Definition::TAG_CONFIGURE,
    ];

    /**
     * 剧本的名称.
     * @var string
     */
    protected $name;

    /*-------- cache ---------*/

    /**
     * @var UnheardRegistrar|null
     */
    protected $registrar;

    /**
     * @var Episode|null
     */
    protected $config;

    /**
     * @var EpisodeMem|null
     */
    protected $episodeMem;
    /**
     * EpisodeTask constructor.
     * @param string $episodeName
     */
    public function __construct(string $episodeName)
    {
        $this->name = $episodeName;
        parent::__construct([]);
    }

    /*--------- 系统方法 ---------*/

    public function __sleep(): array
    {
        $fields = parent::__sleep(); // TODO: Change the autogenerated stub
        $fields[] = 'name';
        return $fields;
    }

    public static function __depend(Depending $depending): void
    {
    }

    public function __exiting(Exiting $listener): void
    {
        $listener->onBackward(function(Dialog $dialog){
            $dialog->say()->warning('unheardLike.dialog.noBackward');
            return $dialog->rewind();
        })
        ->onCancel(function(Dialog $dialog) {
            $dialog->say()->info('unheardLike.dialog.cancel');
            return $dialog->cancel(true);
        });
    }

    protected static function buildDefinition(): Definition
    {
        throw new ChatbotLogicException(__METHOD__);
    }

    public static function getContextName(): string
    {
        throw new ChatbotLogicException(__METHOD__);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return EpisodeDefinition
     */
    public function getDef(): Definition
    {
        return $this->getRegistrar()->getDef($this->getName());
    }

    protected function getRegistrar() : UnheardRegistrar
    {
        return $this->registrar
            ?? $this->registrar = $this->getSession()
            ->dialog
            ->app
            ->make(UnheardRegistrar::class);
    }

    public static function registerSelfDefinition(ContainerContract $processContainer): void
    {
        return;
    }

    /*--------- 公共定义 ---------*/

    public function __hearing(Hearing $hearing) : void
    {
        $hearing

            ->defaultFallback(function(Dialog $dialog){
                $dialog->say()->warning('unheardLike.dialog.missed');
                return $dialog->rewind();
            })

            // 后于正常逻辑执行.
            ->component(function(Hearing $hearing) {
                $commands = $this->episode->commands;

                // 公共匹配部分逻辑.
                $hearing
                    ->is($commands->quit, Redirector::goCancel())
                    ->is($commands->calling, Redirector::goStage('calling'))
                    ->is($commands->rewind, Redirector::goStage('initialize'))
                    ->is($commands->follow, Redirector::goStage('chooseFollow'))
                    ->is($commands->mark, Redirector::goStage('mark'));

            });
    }

    /*--------- 正式流程 ---------*/

    /**
     * 游戏开始.
     * 介绍本章流程.
     *
     * @param Stage $stage
     * @return Navigator
     */
    public function __onStart(Stage $stage): Navigator
    {
        // 继续之前的进度.
        $t = $this->mem->t;
        $win = $this->mem->win;
        $dialog = $stage->dialog;

        // 完成过游戏
        if ($win) {
            $dialog->say()->info('unheardLike.dialog.restart');
            return $dialog->goStage('initialize');
        }

        // 有历史进度
        if (!empty($t)) {
            $dialog->say()->info('unheardLike.dialog.continuePlay');
            return $dialog->goStage('play');
        }

        // 生成连续对话脚本
        $scripts = array_map(function(string $introduce) {

            $intros = explode('|', $introduce);

            $talker = Talker::say($this->getLineSlots());

            foreach ($intros as $intro) {
                $talker->info($this->episode->messagePrefix . trim($intro));
            }

            return $talker;
        }, $this->episode->introduces);

        // 自动延续对话脚本.
        $askContinue = new AskContinue(
            $scripts,
            'unheardLike.dialog.askContinue',
            $this->episode->commands->toContinue
        );

        $askContinue->onFinal(Redirector::goStage('confirmStart'));
        $askContinue->onHelp(function(Dialog $dialog){
            $dialog->say()->warning('unheardLike.dialog.finishIntro');
            return $dialog->rewind();
        });
        return $stage->component($askContinue);
    }

    /**
     * 确认开始游戏.
     * @param Stage $stage
     * @return Navigator
     */
    public function __onConfirmStart(Stage $stage) : Navigator
    {
        return $stage->buildTalk()
            ->askConfirm('unheardLike.dialog.confirmStart')
            ->hearing()
            ->isPositive(Redirector::goStage('initialize'))
            ->isNegative(Redirector::goCancel())
            ->end();
    }


    /**
     * 初始化当前进度. 但不清空记忆里的内容.
     * @param Stage $stage
     * @return Navigator
     */
    public function __onInitialize(Stage $stage) : Navigator
    {
        // 数据初始化.
        $episode = $this->episode;
        $initialize = $episode->initialize;
        $this->mem->t = $t = $initialize->time;
        $follow = $this->mem->follow;
        if (empty($follow)) {
            $this->mem->follow = $follow = $initialize->follow;
        }

        $this->mem->at = $episode
            ->getFrames()
            ->get($t)
            ->getRoleRoom($follow);


        $dialog = $stage->dialog;

        $dialog->say([
            'title' => $episode->title,
            'scenes' => implode(', ', $episode->rooms),
            'roles' => implode(', ', $episode->getRoleIds()),
            'names' => implode(', ', $episode->getRolesNames()),
        ])->info('unheardLike.dialog.episodeDesc');


        return $dialog->goStage('play');
    }


    /**
     * 执行游戏逻辑.
     * @param Stage $stage
     * @return Navigator
     */
    public function __onPlay(Stage $stage) : Navigator
    {
        $builder = $stage->onStart(function(Dialog $dialog) : ? Navigator {
                list($time, $at, $lines) = $this->tickFrame(
                    $this->mem->t,
                    $this->mem->follow
                );

                if (empty($lines)) {
                    return $dialog->goStage('final');
                }

                $this->mem->t = $time;
                $this->mem->at = $at;

                $speech = $dialog->say($this->getLineSlots());
                $speech->beginParagraph(PHP_EOL);
                $speech->info('unheardLike.dialog.simpleDescScene');
                foreach ($lines as $line) {
                    $speech->info($this->episode->messagePrefix . trim($line));
                }
                $speech->endParagraph();

                return null;
            })
            ->buildTalk();

        $commands = $this->episode->commands;

        return $builder->askVerbal(
                'unheardLike.dialog.askContinue',
                [
                    $commands->toContinue,
                    $commands->calling,
                ]
            )->hearing()

            // 进行到下一个 frame, 如果没有, 直接进入 final
            ->todo([$this, 'goNext'])
                ->is($commands->toContinue)
                ->isEmpty()
                ->isChoice(0)

            // 将进入 calling, 然后回到当前 stage
            ->todo(Redirector::goStage('calling'))
                ->is($commands->calling)
                ->isChoice(1)

            // 退出游戏.
            ->todo(Redirector::goCancel())
                ->is($commands->quit)
                ->isChoice(2)

            ->todo([$this, 'helpPlay'])
                ->onHelp()

            ->otherwise()
            ->end();
    }

    public function helpPlay(Dialog $dialog) : Navigator
    {
        $dialog->say($this->getLineSlots())->info('unheardLike.dialog.descScene');
        return $dialog->rewind();
    }


    /**
     * 游戏结算环节. 先检查是否完成匹配, 完成了就问问题, 否则就重来.
     * @param Stage $stage
     * @return Navigator
     */
    public function __onFinal(Stage $stage) : Navigator
    {
        $matched = $this->isMarkedAndRight();

        if ($matched) {
            return $stage->dialog->goStage('answer');
        }

        return $stage->dialog->goStage('matchFailed');
    }

    /**
     * 匹配角色错误!
     *
     * @param Stage $stage
     * @return Navigator
     */
    public function __onMatchFailed(Stage $stage) : Navigator
    {
        $init = $this->episode->initialize;
        $this->mem->t = $init->time;

        return $stage->buildTalk()
            ->warning('unheardLike.dialog.matchFailed')
            ->info('unheardLike.dialog.rewind')
            ->goStage('initialize');
    }



    /**
     * 回答答案环节
     * @param Stage $stage
     * @return Navigator
     */
    public function __onAnswer(Stage $stage) : Navigator
    {
        if (! $this->isMarkedAndRight()) {
            $dialog = $stage->dialog;
            $dialog->say()->warning('unheardLike.dialog.matchFailed');
            return $dialog->goStage('calling');
        }

        $questions = $this->episode->questions;
        $isAnswering = $this->isAnswering;
        $total = count($questions);

        if (!isset($isAnswering) || !isset($questions[$isAnswering])) {
            $isAnswering = 0;
        }

        $question = $questions[$isAnswering];

        $choices = $question->choices;
        $choices[] = $back = $this->episode->commands->back;

        return $stage->buildTalk()
            ->askChoose(
                $this->episode->messagePrefix . trim($question->query),
                $choices
            )
            ->hearing()
            ->isAnswer(function(Dialog $dialog, Choice $choice) use ($total, $question, $back) {

                $result = $choice->toResult();
                if ($result === $back) {
                    $this->isAnswering = null;
                    return $dialog->goStage('calling');
                }

                $reply = $question->replies[$choice->getChoice()];
                $reply = $this->episode->messagePrefix . trim($reply);

                if ($result !== $question->answer) {
                    $dialog->say()->warning($reply);
                    return $dialog->goStage('calling');
                }

                $next = $this->isAnswering + 1;

                // 还有问题可以问
                if ($next < $total) {
                    $this->isAnswering = $next;
                    return $dialog->repeat();
                }

                // 到头了, 那就赢了
                $dialog->say()->info('unheardLike.dialog.youWin');
                return $dialog->goStage('win');

            })
            ->end([$this, 'onlyChooseAvailable']);

    }


    public function __onWin(Stage $stage) : Navigator
    {
        $lines = $this->episode->win;
        $prefix = $this->episode->messagePrefix;
        $this->mem->win = true;

        $speech = $stage->dialog->say();

        foreach ($lines as $line) {
            $speech->info($prefix . trim($line));
        }

        return $stage->dialog->fulfill();
    }


    /*--------- 菜单逻辑 ----------*/

    /**
     * 召唤了训练师.
     * @param Stage $stage
     * @return Navigator
     */
    public function __onCalling(Stage $stage) : Navigator
    {
        $commands = $this->episode->commands;

        $menu = [
            1 => $commands->follow,
            2 => $commands->mark,
            3 => $commands->answer,
            4 => $commands->rewind,
            5 => $commands->setTime,
            6 => $commands->back,
        ];

        return $stage->buildTalk()
            ->askChoose(
                'unheardLike.calling.menu',
                $menu
            )
            ->hearing()
            // 选择跟随对象
            ->todo(Redirector::goStage('chooseFollow'))
                ->isChoice(1)

            // 进行角色标注
            ->todo(Redirector::goStage('mark'))
                ->isChoice(2)

            // 进入问答环节.
            ->todo(Redirector::goStage('answer'))
                ->isChoice(3)
                ->isIntent(BreakInt::class)

            // 回到起点
            ->todo(Redirector::goStage('initialize'))
                ->isChoice(4)
                ->isIntent(RewindInt::class)

            // 选择时间点
            ->todo(Redirector::goStage('setTime'))
                ->isChoice(5)

            // 继续玩.
            ->todo(Redirector::goStage('play'))
                ->isChoice(6)
                ->isIntent(BackwardInt::class)
            ->end();


    }


    public function __onSetTime(Stage $stage) : Navigator
    {
        $back = $this->episode->commands->back;
        return $stage->buildTalk()
            ->askVerbal(
                'unheardLike.calling.askSetTime',
                [
                    '00:05',
                    $back
                ]
            )
            ->hearing()
            ->todo(Redirector::goStage('play'))
                ->is($back)
                ->isChoice(1)
            ->todo(function(Dialog $dialog, VerbalMsg $message) {
                $text = $message->getTrimmedText();

                // 设置时间
                if (preg_match('/^[0-9]{2}:[0-9]{2}$/', $text)) {
                    $this->mem->t = $this->episode->searchNearFrame($text);
                    return $dialog->goStage('play');
                }

                // 格式不正确
                $dialog->say()->warning('unheardLike.calling.wrongType');
                return $dialog->repeat();
            })
                ->isInstanceOf(VerbalMsg::class)
            ->end();

    }

    /**
     *
     * @param Stage $stage
     * @return Navigator
     */
    public function __onChooseFollow(Stage $stage) : Navigator
    {
        /**
         * @var Frame $frame
         */
        $frame = $this->episode->getFrames()->get($this->mem->t);
        $at = $this->mem->at;
        $choices = [];
        foreach ($frame->roleRooms as $role => $room) {
            if ($at === $room && $role !== $this->mem->follow) {
                $choices[] = $role;
            }
        }

        if (empty($choices)) {
            $stage->dialog->say()->warning('unheardLike.calling.noTargetToFollow');
            return $stage->dialog->goStage('play');
        }

        $commands = $this->episode->commands;
        $choices[] = $commands->back;

        return $stage
            ->buildTalk()
            ->askChoose(
                'unheardLike.calling.chooseToFollow',
                $choices
            )
            ->hearing()
            ->is($commands->back, Redirector::goStage('play'))
            ->isAnswer(function(Dialog $dialog, Choice $choice) use ($choices){

                $last = end($choices);
                $result = $choice->toResult();
                if ($result === $last) {
                    return $dialog->goStage('play');
                }

                if (in_array($result, $this->episode->getRoleIds())) {
                    $this->mem->follow = $result;
                    return $dialog->goStage('play');
                }

                return null;
            })
            ->end([$this, 'onlyChooseAvailable']);
    }

    /**
     * 标记人员.
     * @param Stage $stage
     * @return Navigator
     */
    public function __onMark(Stage $stage) : Navigator
    {
        $choices = $this->episode->getRoleIds();
        $marked = $this->mem->getMarked();
        $back = $this->episode->commands->back;
        $choices[] = $back;

        return $stage->buildTalk()
            ->info('unheardLike.calling.marked', [
                'marked' => $marked
            ])
            ->askChoose(
                'unheardLike.calling.chooseToMark',
                $choices
            )
            ->hearing()
            ->isAnswer(function(Dialog $dialog, Choice $choice) use ($choices){

                $last = end($choices);
                $result = $choice->toResult();
                if ($result === $last) {
                    return $dialog->goStage('play');
                }

                $this->isMarking = $result;
                return $dialog->goStage('markToRole');
            })
            ->end([$this, 'onlyChooseAvailable']);

    }

    public function __onMarkToRole(Stage $stage) : Navigator
    {
        if (empty($this->isMarking)) {
            return $stage->dialog->goStage('play');
        }

        $choices = $this->episode->getRolesNames();
        $back = $this->episode->commands->back;
        $choices[] = $back;
        return $stage->buildTalk([
                'marking' => $this->isMarking,
            ])
            ->askChoose(
                'unheardLike.calling.chooseRoleToMark',
                $choices
            )
            ->hearing()
            ->isAnswer(function(Dialog $dialog, Choice $choice) use ($choices){

                $last = end($choices);
                $result = $choice->toResult();
                if ($result === $last) {
                    return $dialog->goStage('play');
                }


                $marked = $this->mem->marked;

                // 撤掉原有的.
                $index = array_search($result, $marked);
                if ($index !== false) {
                    unset($marked[$index]);
                }

                $marked[$this->isMarking] = $result;
                $this->mem->marked = $marked;

                $this->isMarking = null;

                return $dialog->goStage('mark');
            })
            ->end([$this, 'onlyChooseAvailable']);

    }


    public function onlyChooseAvailable(Dialog $dialog) : Navigator
    {
        $dialog->say()->warning('unheardLike.calling.onlyChooseAvailable');
        return $dialog->rewind();
    }





    /*--------- 流程方法 ---------*/



    public function goNext(Dialog $dialog) : Navigator
    {
        $current = $this->mem->t;
        /**
         * @var Frame $frame;
         */
        $frame = $this->episode->getFrames()->get($current);
        if (empty($frame)) {
            return $dialog->goStage('final');
        }

        $next = $frame->next;
        if (empty($next)) {
            return $dialog->goStage('final');
        }
        $this->mem->t = $next;

        return $dialog->repeat();
    }

    /**
     * @param string $t
     * @param string $follow
     * @return array  [ string $time, string $at, string[] $lines]
     */
    public function tickFrame(string $t, string $follow) : array
    {
        $frames = $this->episode->getFrames();

        do {
            /**
             * @var Frame|null $frame
             */
            $frame = $frames->get($t);

            // 切换位置.
            $at = $frame->getRoleRoom($follow);

            // 看看当前帧是否有内容.
            $lines = $frame->getRoomLines($at);

            // 有内容直接返回.
            if (!empty($lines)) {
                break;
            }

            $t = $frame->next;

        } while ($t);

        return [$t, $at, $lines];
    }

    protected function getLineSlots() : array
    {
        $map = $this->episode->getCharacterMap();

        $marked = $this->mem->marked;
        $slots = [];

        foreach ($map as $role => $character) {
            $desc = $character->desc;
            $mark = $marked[$role] ?? $role;
            $info = "$mark ($desc)";
            $slots["r_$role"] = $info;
            $slots["m_$role"] = $mark;
        }

        $commands = $this->episode->commands;

        $slots = $slots + [
            'time' => $this->mem->t,
            'follow' => $this->mem->follow,
            'at' => $this->mem->at,
            'hasMarked' => $this->mem->getMarked(),
            // commands
            'c_calling' => $commands->calling,
            'c_quit' => $commands->quit,
            'c_mark' => $commands->mark,
            'c_answer'=> $commands->answer,
            'c_follow' => $commands->follow,
            'c_rewind' => $commands->rewind,
        ];

        return $slots;
    }

    protected function isMarkedAndRight() : bool
    {
        $marked = $this->mem->marked;
        foreach ($this->episode->characters as $role) {
            $id = $role->id;

            $matched = isset($marked[$id]) && $marked[$id] === $role->name;
            if (! $matched) {
                return false;
            }
        }
        return true;
    }

    /*--------- getter 方法 ---------*/

    public function __getEpisode() : Episode
    {
        return $this->config
            ?? $this->config = $this->getDef()->getEpisode();

    }

    public function __getMem() : EpisodeMem
    {
        return $this->episodeMem
            ?? $this->episodeMem = (new EpisodeMem(
                $this->episode->id,
                $this->getSession()->conversation->getChat()->getUserId()
            ))->toInstance($this->getSession());
    }

}