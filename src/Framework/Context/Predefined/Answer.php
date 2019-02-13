<?php

/**
 * Class Answer
 * @package Commune\Chatbot\Host\Context\Predefined
 */

namespace Commune\Chatbot\Framework\Context\Predefined;


use Commune\Chatbot\Framework\Context\Context;
use Commune\Chatbot\Framework\Context\ContextCfg;
use Commune\Chatbot\Framework\Conversation\Scope;
use Commune\Chatbot\Framework\Routing\DialogRoute;
use Commune\Chatbot\Framework\Intent\Intent;
use Commune\Chatbot\Framework\Message\Questions\Ask;
use Commune\Chatbot\Framework\Support\TypeTransfer;

class Answer extends ContextCfg
{
    const SCOPE = [
        Scope::MESSAGE
    ];

    const DATA = [
        'answer' => '',
        'fulfill' => false
    ];

    const PROPS = [
        'question' => '',
        'default' => ''
    ];

    public function prepared(Context $context)
    {
        $context->reply(new Ask($context['question'], $context['default']));
    }

    public function routing(DialogRoute $route)
    {
        $route->fallback()
            ->action(function(Context $context, Intent $intent){
                $text = $intent->getMessage()->getText();
                $text = !empty($text) ? $text : $context['default'];
                $context['answer'] = $text;
            })
            ->intended();
    }

    public function toString(Context $context) : string
    {
        return TypeTransfer::toString($context['answer']);
    }


}