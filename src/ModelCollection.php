<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\collection;

use function call_user_func;
use Yii;
use yii\base\Arrayable;
use yii\base\InvalidCallException;
use yii\db\ActiveQuery;
use yii\db\ActiveRecordInterface;
use yii\db\BaseActiveRecord;
use yii\db\Connection;
use yii\di\Instance;
use yii\helpers\Json;

/**
 * ModelCollection
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 1.0
 */
class ModelCollection extends Collection
{
    /**
     * @var ActiveQuery|null the query that returned this collection.
     * May be`null` if the collection has not been created by a query.
     */
    public $query;

    /**
     * Collection constructor.
     * @param array|null $models
     * @param array $config
     */
    public function __construct($models = [], $config = [])
    {
        //ensure that each instance of BaseActiveRecord or ActiveRecordInterface ?
        parent::__construct($models, $config);
    }

    /**
     * @return array|BaseActiveRecord[]|ActiveRecordInterface[]|Arrayable[] models contained in this collection.
     */
    public function getModels()
    {
        return $this->getData();
    }

    // TODO relational operations like link() and unlink() sync()
    // https://github.com/yiisoft/yii2/pull/12304#issuecomment-242339800
    // https://github.com/yiisoft/yii2/issues/10806#issuecomment-242346294

    // TODO addToRelation() by checking if query is a relation
    // https://github.com/yiisoft/yii2/issues/10806#issuecomment-241505294


    // https://github.com/yiisoft/yii2/issues/12743
    public function findWith($with)
    {
        if (!$this->query) {
            throw new InvalidCallException('This collection was not created from a query, so findWith() is not possible.');
        }
        $models = $this->getData();
        $this->query->findWith($with, $models);
        return $this;
    }
    
    // AR specific stuff

    /**
     * https://github.com/yiisoft/yii2/issues/13921
     *
     * TODO add transaction support
     */
    public function deleteAll($useTransaction = false, $db = 'db')
    {
        if($useTransaction === true){
           return $this->inTransaction($db, 'deleteAll');
        }
        foreach($this->getData() as $model) {
            $model->delete();
        }
        // return $this ?
    }

    public function scenario($scenario)
    {
        foreach($this->getData() as $model) {
            $model->scenario = $scenario;
        }
        return $this;
    }

    /**
     * https://github.com/yiisoft/yii2/issues/13921
     *
     * TODO add transaction support
     */
    public function updateAll($attributes, $safeOnly = true, $runValidation = true, $useTransaction = false, $db = 'db')
    {
        if($useTransaction === true){
            return $this->inTransaction($db, 'updateAll', [$attributes, $safeOnly, $runValidation]);
        }
        foreach($this->getData() as $model) {
            $model->setAttributes($attributes, $safeOnly);
            $model->update($runValidation, array_keys($attributes));
        }
        return $this;
    }

    public function insertAll()
    {
        // TODO could be a batch insert
        return $this;
    }

    public function saveAll($runValidation = true, $attributeNames = null, $useTransaction = false, $db = 'db')
    {
        if($useTransaction === true){
            return $this->inTransaction($db, 'saveAll', [$runValidation, $attributeNames]);
        }
        foreach($this->getData() as $model) {
            $model->save($runValidation, $attributeNames);
        }
        return $this;
    }

    /**
     * https://github.com/yiisoft/yii2/issues/10806#issuecomment-242119472
     *
     * @return bool
     */
    public function validateAll()
    {
        $success = true;
        foreach($this->getData() as $model) {
            if (!$model->validate()) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * @param array $fields
     * @param array $expand
     * @param bool $recursive
     * @return Collection|static
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true)
    {
        return $this->map(function($model) use ($fields, $expand, $recursive) {
            /** @var $model Arrayable */
            return $model->toArray($fields, $expand, $recursive);
        });
    }

    /**
     * Encodes the collected models into a JSON string.
     * @param int $options the encoding options. For more details please refer to
     * <http://www.php.net/manual/en/function.json-encode.php>. Default is `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
     * @return string the encoding result.
     */
    public function toJson($options = 320)
    {
        return Json::encode($this->toArray()->getData(), $options);
    }

    protected function ensureData($data)
    {
        if($data === null){
            if ($this->query === null) {
                throw new InvalidCallException('This collection was not created from a query.');
            }
            return $this->query->all();
        }
        return parent::ensureData($data); // TODO: Change the autogenerated stub
    }

    private function inTransaction($db, $method, $args = [])
    {
        $db = Instance::ensure($db, Connection::class);
        $transaction = $db->beginTransaction();
        try{
            return $this->$method(...$args);
        }catch (\Throwable $e){
            $transaction->rollBack();
            throw $e;
        }
    }
}
