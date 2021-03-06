<?php


namespace Commune\Components\SimpleChat\Providers;


use Commune\Chatbot\Blueprint\ServiceProvider;
use Commune\Chatbot\Contracts\ConsoleLogger;
use Commune\Chatbot\OOHost\Context\Contracts\RootIntentRegistrar;
use Commune\Chatbot\OOHost\Context\Intent\PlaceHolderIntentDef;
use Commune\Chatbot\OOHost\NLU\Contracts\Corpus;
use Commune\Chatbot\OOHost\NLU\Options\IntentCorpusOption;
use Commune\Components\SimpleChat\Options\ChatOption;
use Commune\Support\OptionRepo\Contracts\OptionRepository;

class RegisterSimpleChat extends ServiceProvider
{
    const IS_PROCESS_SERVICE_PROVIDER = true;

    public function boot($app)
    {
        /**
         * @var OptionRepository $repo
         * @var RootIntentRegistrar $intRepo
         * @var ChatOption $option
         * @var ConsoleLogger $logger
         * @var Corpus $corpus
         */
        $repo = $app[OptionRepository::class];
        $intRepo = $app[RootIntentRegistrar::class];
        $logger = $app[ConsoleLogger::class];
        $corpus = $app[Corpus::class];
        $manager = $corpus->intentCorpusManager();

        foreach ($repo->eachOption(ChatOption::class) as $option) {
            $name = $option->intent;

            // 注册尚不存在的意图.
            if (!$intRepo->hasDef($name)) {
                $intRepo->registerDef(new PlaceHolderIntentDef($name), false);
                $logger->debug('simple chat register placeHolderIntent [' . $name .']');
            }

            // 注册尚不存在的例句.
            $chatExamples = $option->examples;
            if (empty($chatExamples)) {
                continue;
            }

            /**
             * @var IntentCorpusOption $corpusOption
             */
            $corpusOption = $manager->get($name);
            $examples = $corpusOption->examples;
            if (empty($examples)) {
                $corpusOption->mergeExamples($chatExamples);
                $logger->debug('simple chat register examples for ' . $name);
            }
            // 不会主动同步到 repository
        }


    }

    public function register()
    {
    }


}