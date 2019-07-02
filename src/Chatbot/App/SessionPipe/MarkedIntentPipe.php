<?php


namespace Commune\Chatbot\App\SessionPipe;


use Commune\Chatbot\OOHost\Session\Session;
use Commune\Chatbot\OOHost\Session\SessionPipe;
use Illuminate\Support\Collection;

class MarkedIntentPipe implements SessionPipe
{
    public function handle(Session $session, \Closure $next): Session
    {
        $intent = $session->getMatchedIntent();
        if (isset($intent)) {
            return $next($session);
        }

        $text = $session->incomingMessage->message->getTrimmedText();

        if (!preg_match('/^#[\w\.:]+#$/', $text)) {
            return $next($session);
        }

        $intentName = trim($text, '#');
        if ($session->intentRepo->has($intentName)) {
            $incoming = $session->incomingMessage;
            $incoming->addPossibleIntent(
                    $intentName,
                    new Collection([]),
                    100
                );

            $highlyPossible = $incoming->getHighlyPossibleIntentNames();
            $highlyPossible[] = $intentName;
            $incoming->setHighlyPossibleIntentNames($highlyPossible);
        }

        return $next($session);
    }


}