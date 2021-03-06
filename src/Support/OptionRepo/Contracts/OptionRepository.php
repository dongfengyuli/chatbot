<?php

namespace Commune\Support\OptionRepo\Contracts;

use Commune\Support\OptionRepo\Options\CategoryMeta;
use Commune\Support\Option;
use Commune\Support\OptionRepo\Exceptions\OptionNotFoundException;
use Commune\Support\OptionRepo\Exceptions\RepositoryMetaNotExistsException;
use Commune\Support\OptionRepo\Exceptions\SynchroniseFailException;


/**
 * 配置的抽象层.
 * 用若干个 option storage 存储介质搭建一个管道, 从管道中读取 option 对象.
 *
 * 这样做的目的是实现一个可替换存储介质的分布式配置中心.
 * 可以实现:
 *
 * - 多介质自动更新配置
 * - 配置存储介质可以迁移
 * - 组件化提供缓存层
 *
 * 如果系统的默认配置在 etcd, 则与 etcd 高度耦合. 没有 etcd 的场景甚至无法启动.
 * 一些开源项目可以把配置写在 yaml 中, 用仓库直接传播. 但却无法实现线上多服务器同步修改.
 *
 * 有一个抽象层, 把 etcd, yaml 等都当做存储介质, 通过一个管道读写, 则可以解决这种问题.
 *
 * 注意: 如果配置数量极多, 还是用别的方式比较合适. OptionRepository 只适合管理数量级比较小的配置.
 *
 */
interface OptionRepository
{
    /*---- meta ----*/

    /**
     * 注册一个 option 分类的元数据.
     * @param CategoryMeta $meta
     */
    public function registerCategory(CategoryMeta $meta) : void;

    /**
     * 获取一个 option 分类的元数据. 不存在会抛出异常.
     *
     * @param string $category
     * @return CategoryMeta
     * @throws RepositoryMetaNotExistsException
     */
    public function getCategoryMeta(string $category) :  CategoryMeta;

    /**
     * 检查分类是否存在.
     *
     * @param string $category
     * @return bool
     */
    public function hasCategory(string $category) : bool ;

    /*---- 单个 option 管理 ----*/

    /**
     * 检查 option 是否存在.
     *
     * @param string $category  分类名
     * @param string $optionId  option id
     * @throws RepositoryMetaNotExistsException  如果分类不存在抛出异常
     * @return bool
     */
    public function has(
        string $category,
        string $optionId
    ) : bool;

    /**
     * 获取一个 option
     *
     * @param string $category  分类名
     * @param string $optionId  option id
     * @return Option
     *
     * @throws RepositoryMetaNotExistsException 分类不存在抛出异常
     * @throws OptionNotFoundException option不存在也抛出异常
     */
    public function find(
        string $category,
        string $optionId
    ) : Option;

    /**
     * 获取一个 option 在所有 storage 中的版本.
     * 可以用于检查存储介质是否发生了不一致.
     *
     * @param string $category
     * @param string $optionId
     * @return Option[]
     */
    public function findAllVersions(
        string $category,
        string $optionId
    ) : array;

    /**
     * 更新, 或者创建一个 option.
     * 会先存储到 root storage, 然后从上往下一层层存储.
     * meta 必须存在.
     *
     * @param string $category
     * @param Option $option
     * @param bool $draft 如果是草稿, 则不会立刻同步.
     *
     * @throws RepositoryMetaNotExistsException
     * @throws OptionNotFoundException
     * @throws SynchroniseFailException
     */
    public function save(
        string $category,
        Option $option,
        bool $draft = false
    ) : void;


    /**
     * 更新, 或者创建一批 option. meta 必须存在.
     *
     * @param string $category
     * @param bool $draft 是否立刻同步.
     * @param Option[] $options
     *
     * @throws RepositoryMetaNotExistsException
     * @throws OptionNotFoundException
     * @throws SynchroniseFailException
     */
    public function saveBatch(
        string $category,
        bool $draft,
        Option ...$options
    ) : void;

    /**
     * 同步整个 category 所有的 option
     * 遍历 root storage 的存储, 同步给所有 storage
     *
     * @param string $category
     */
    public function syncCategory(
        string $category
    ) : void;

    /**
     * 删除掉一个 option
     *
     * @param string $category
     * @param string[] $ids
     * @throws RepositoryMetaNotExistsException
     */
    public function delete(
        string $category,
        string ...$ids
    ) : void;

    /**
     * 强制同步一个 option
     *
     * @param string $category
     * @param string $id
     *
     * @throws RepositoryMetaNotExistsException
     * @throws OptionNotFoundException
     * @throws SynchroniseFailException
     */
    public function sync(
        string $category,
        string $id
    ) : void;



    /*---- 多个 option 管理 ----*/
    /**
     * 获取一种 option 存储的总数.
     *
     * @param string $category
     * @return int
     */
    public function count(
        string $category
    ) : int;

    /**
     * 分页列举一种 option 的 id => brief
     *
     * brief 来自 Option::getBrief 方法
     *
     * @param string $category
     * @param int $page
     * @param int $lines
     * @return string[]
     */
    public function paginateIdToBrief(
        string $category,
        int $page = 1,
        int $lines = 20
    ) : array;


    /**
     * 取出所有 option 的ID
     *
     * @param string $category
     * @return array
     */
    public function getAllOptionIds(
        string $category
    ) : array;


    /**
     * 使用 id 数组, 取出相关 option 的 map
     *
     * @param string $category
     * @param array $ids
     * @return Option[]
     */
    public function findOptionsByIds(
        string $category,
        array $ids
    ) : array;


    /**
     * 使用关键词从 option brief 中搜索 option
     * query 是什么就不关心了. 由root storage 自身决定.
     *
     * @param string $category
     * @param string $query
     * @return Option[]
     */
    public function searchInBriefs(
        string $category,
        string $query
    ) : array;


    /**
     * 遍历一个 category 下所有的 option 实例.
     *
     * @param string $category
     * @return \Generator
     */
    public function eachOption(
        string $category
    ) : \Generator;
}