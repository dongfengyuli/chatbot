<?php


namespace Commune\Chatbot\Framework\Conversation;


use Commune\Chatbot\Blueprint\Conversation\Chat;
use Commune\Chatbot\Contracts\CacheAdapter;

class ChatImpl implements Chat
{
    /**
     * @var string
     */
    protected $platformId;

    /**
     * @var string
     */
    protected $userId;

    /**
     * @var string
     */
    protected $chatbotUserName;

    /**
     * @var string
     */
    protected $chatId;

    /**
     * @var CacheAdapter
     */
    protected $cache;

    /**
     * @var string|null
     */
    protected $scene;

    /**
     * ChatImpl constructor.
     * @param CacheAdapter $cacheAdapter
     * @param string $platformId
     * @param string $userId
     * @param string $chatbotUserName
     * @param null|string $scene
     * @param string|null $chatId
     */
    public function __construct(
        CacheAdapter $cacheAdapter,
        string $platformId,
        string $userId,
        string $chatbotUserName,
        ?string $scene,
        string $chatId = null
    )
    {
        $this->cache = $cacheAdapter;
        $this->platformId = $platformId;
        $this->userId = $userId;
        $this->chatbotUserName = $chatbotUserName;
        $this->scene = $scene;
        $sceneStr = isset($scene) ? ":$scene" : '';
        $this->chatId = $chatId ?? md5("p:$platformId:u:$userId:c:$chatbotUserName:$sceneStr");


    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getChatbotName(): string
    {
        return $this->chatbotUserName;
    }

    public function getPlatformId(): string
    {
        return $this->platformId;
    }

    public function getChatId(): string
    {
        return $this->chatId;
    }

    /**
     * @return null|string
     */
    public function getScene(): ?string
    {
        return $this->scene;
    }

    public function lock(int $ttl = null): bool
    {
        return $this->cache->lock($this->getChatLockerKey($this->getChatId()), $ttl);
    }

    public function unlock(): void
    {
        $this->cache->unlock($this->getChatLockerKey($this->getChatId()));
    }

    public function getChatLockerKey(string $chatId) : string
    {
        return "chatbot:chatLocker:" . $chatId;
    }

}