<?php


namespace Commune\Chatbot\App\SessionPipe;


use Commune\Chatbot\Config\ChatbotConfig;
use Commune\Chatbot\OOHost\Context\Intent\IntentMessage;
use Commune\Chatbot\OOHost\Session\Session;
use Commune\Chatbot\OOHost\Session\SessionPipe;

class NavigationPipe implements SessionPipe
{
    protected $navigationIntents = [];

    public function __construct(ChatbotConfig $config)
    {
        $this->navigationIntents = $config->host->navigatorIntents;
    }

    public function handle(Session $session, \Closure $next): Session
    {
        $navigation = $this->navigationIntents;

        if (empty($navigation)) {
            return $next($session);
        }

        // 检查matched
        $intent = $session->getMatchedIntent();
        if (isset($intent)) {
            // 命中的话也是直接执行.
            return in_array($intent->getName(), $navigation)
                ? $this->runIntent($intent, $session)
                : $next($session);
        }

        $repo = $session->intentRepo;
        foreach ($navigation as $intentName) {

            // 匹配到了.
            $intent = $repo->matchIntent($intentName, $session);
            if (isset($intent)) {
                return $this->runIntent($intent, $session);
            }
        }
        return $next($session);
    }

    protected function runIntent(IntentMessage $intent, Session $session) : Session
    {
        $session->hear(
            $session->incomingMessage->message,
            $intent->navigate($session->dialog)
        );
        return $session;
    }


}