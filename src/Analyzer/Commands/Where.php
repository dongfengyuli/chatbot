<?php

/**
 * Class Where
 * @package Commune\Chatbot\Analyzer\Commands
 */

namespace Commune\Chatbot\Analyzer\Commands;


use Commune\Chatbot\Analyzer\AnalyzerCommand;
use Commune\Chatbot\Contracts\ChatbotApp;
use Commune\Chatbot\Contracts\SessionDriver;
use Commune\Chatbot\Framework\Conversation\Conversation;
use Commune\Chatbot\Framework\Routing\Router;
use Commune\Chatbot\Framework\Session\Session;
use Commune\Chatbot\Framework\Message\Text;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;

class Where extends AnalyzerCommand
{

    protected $signature = 'where';

    protected $description = '';

    protected $app;

    protected $driver;

    protected $log;

    protected $router;

    public function __construct(
        ChatbotApp $app,
        SessionDriver $driver,
        LoggerInterface $log,
        Router $router
    )
    {
        $this->app = $app;
        $this->driver = $driver;
        $this->log = $log;
        $this->router = $router;
        parent::__construct();
    }

    public function handle(InputInterface $input, Conversation $conversation): Conversation
    {
        $session = new Session(
            $this->app,
            $this->driver,
            $this->log,
            $this->router,
            $conversation
        );

        $location = $session->getHistory()->current();
        $context = $session->fetchContextByLocation($location);

        $conversation->reply(new Text($context->toJson()));
        return $conversation;
    }


}