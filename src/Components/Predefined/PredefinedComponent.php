<?php


namespace Commune\Components\Predefined;


use Commune\Chatbot\Framework\Component\ComponentOption;
use Commune\Chatbot\OOHost\NLU\Options\EntityDictOption;
use Commune\Chatbot\OOHost\NLU\Options\IntentCorpusOption;

/**
 * 系统预定义的组件. 提供系统默认的意图, 并将相关语料加载到本地语料库.
 *
 * @property-read string $intentCorpusFile
 * @property-read string $entityDictFile
 */
class PredefinedComponent extends ComponentOption
{

    public static function stub(): array
    {
        return [
            'intentCorpusFile' => __DIR__ . '/resources/intents.yml',
            'entityDictFile' => __DIR__ . '/resources/entities.yml',
        ];
    }

    protected function doBootstrap(): void
    {
        $namespace = "Commune\\Components\\Predefined\\";
        $path = __DIR__  ;

        $this->loadSelfRegisterByPsr4(
            $namespace,
            $path
        );

        $path = $this->intentCorpusFile;
        if (!empty($path)) {
            $this->registerCorpusOptionFromYaml(
                $path,
                IntentCorpusOption::class
            );
        }

        $path = $this->entityDictFile;
        if (!empty($path)) {
            $this->registerCorpusOptionFromYaml(
                $path,
                EntityDictOption::class
            );
        }
    }



}