<?php


namespace Commune\Chatbot\OOHost\Session;


use Commune\Chatbot\OOHost\Context\Memory\Memory;
use Commune\Chatbot\OOHost\Context\Contracts\RootMemoryRegistrar;

/**
 * 用 array 的方式来调用. 例如 $this->session->memory['sandbox']
 */
class SessionMemory implements \ArrayAccess
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Memory[]
     */
    protected $memories = [];

    /**
     * SessionMemory constructor.
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }


    protected function getRepo() : RootMemoryRegistrar
    {
        return $this->session->memoryRepo;
    }

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->memories[$offset]) || $this->getRepo()->hasDef($offset);
    }

    /**
     * @param string $offset
     * @return Memory|null
     */
    public function offsetGet($offset)
    {
        $repo = $this->getRepo();

        if (isset($this->memories[$offset])) {
            return $this->memories[$offset];
        }

        if (!$repo->hasDef($offset)) {
            return null;
        }

        /**
         * @var Memory $memory
         */
        $memory = $repo->getDef($offset)->newContext()->toInstance($this->session);
        return $this->memories[$offset] = $memory;

    }

    public function offsetSet($offset, $value)
    {
        if (!$value instanceof Memory) {
            throw new \InvalidArgumentException(
                __METHOD__
                . ' only accept '.Memory::class
            );
        }
        $this->memories[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->memories[$offset]);
    }


}