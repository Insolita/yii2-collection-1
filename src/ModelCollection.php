<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\collection;

use function get_class;
use Yii;
use yii\base\Arrayable;
use yii\base\InvalidCallException;
use yii\base\InvalidValueException;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
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
        //probably we should ensure here that each item is BaseActiveRecordInstance
        return $this->getData();
    }

    /**
     * return collection filled by query
     * @return $this
     */
    public function reload()
    {
        return new static($this->ensureData(null), ['query'=>$this->query]);
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
        $this->ensureAllInstanceOf(BaseActiveRecord::class);
        $models = $this->getData();
        $this->query->findWith($with, $models);
        return $this;
    }

    // AR specific stuff

    /**
     * https://github.com/yiisoft/yii2/issues/13921
     * TODO add transaction support
     * @param bool   $useTransaction
     * @param string $db
     * @return
     * @throws \Throwable
     */
    public function deleteAll($useTransaction = false, $db = 'db')
    {
        $this->ensureAllInstanceOf(BaseActiveRecord::class);
        if($useTransaction === true){
           return $this->runTransaction($db, 'deleteAll');
        }
        foreach($this->getModels() as $model) {
            $model->delete();
        }
        // return $this ?
    }

    /**
     * @param $scenario
     * @return $this
     */
    public function scenario($scenario)
    {
        $this->ensureAllInstanceOf(BaseActiveRecord::class);
        foreach($this->getModels() as $model) {
            $model->scenario = $scenario;
        }
        return $this;
    }

    /**
     * Fill all models with common attributes
     * @param      $attributes
     * @param bool $safeOnly
     * @param string|null $scenario
     * @return $this
     */
    public function fillAll($attributes, $safeOnly = true, $scenario = null)
    {
        $this->ensureAllInstanceOf(BaseActiveRecord::class);
        if(!empty($attributes)){
            foreach($this->getModels() as $model) {
                if($scenario){
                    $model->scenario = $scenario;
                }
                $model->setAttributes($attributes, $safeOnly);
            }
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
        $this->ensureAllInstanceOf(BaseActiveRecord::class);
        $success = true;
        foreach($this->getModels() as $model) {
            if (!$model->validate()) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * https://github.com/yiisoft/yii2/issues/13921
     * @param bool              $runValidation
     * @param array|null        $attributeNames
     * @param bool              $useTransaction
     * @param string|Connection $db used for transaction
     * @return $this
     */
    public function updateAll($runValidation = true, $attributeNames = null, $useTransaction = false, $db = 'db')
    {
        $this->ensureAllInstanceOf(BaseActiveRecord::class);
        if($useTransaction === true){
            return $this->runTransaction($db, 'updateAll', [$runValidation, $attributeNames]);
        }
        foreach ($this->getModels() as $model){
            if($model->isNewRecord){
                $model->save($runValidation, $attributeNames);
            }else{
                $model->update($runValidation, $attributeNames);
            }
        }
        return $this;
    }

    /**
     * @param bool       $runValidation
     * @param null|array $attributeNames
     * @param bool       $useTransaction
     * @param string     $db
     * @return $this
     * @throws \Throwable
     */
    public function saveAll($runValidation = true, $attributeNames = null, $useTransaction = false, $db = 'db')
    {
        $this->ensureAllInstanceOf(BaseActiveRecord::class);
        if($useTransaction === true){
            return $this->runTransaction($db, 'saveAll', [$runValidation, $attributeNames]);
        }
        foreach($this->getModels() as $model) {
            $model->save($runValidation, $attributeNames);
        }
        return $this;
    }


    public function insertAll()
    {
        /**@var \yii\db\ActiveRecord $model*/
        $model = $this->values()->offsetGet(0);
        if(!$model instanceof ActiveRecord){
            throw new InvalidCallException('Only \yii\db\ActiveRecord supported');
        }
        $this->ensureAllInstanceOf(get_class($model));
        return $model::getDb()->createCommand()
                       ->batchInsert(
                           $model::tableName(),
                           array_keys($model->getAttributes()),
                           $this->values()->map(function($model){
                               return array_values($model->getAttributes());
                           })->getData()
                       )->execute();
    }

    public function addToRelation(ActiveRecord $model, $relationName)
    {
         foreach ($this->getModels() as $related){
             $model->link($relationName, $related);
         }
    }



    /**
     * @param string $className
     */
    public function ensureAllInstanceOf($className = ActiveRecord::class)
    {
        $this->each(function($item) use ($className){
            if(!$item instanceof $className){
                throw new InvalidValueException('Collection must contains only instances of '.$className);
            }
        });
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
        return Json::encode($this->toArray()->getModels(), $options);
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

    private function runTransaction($db, $method, $args = [])
    {
        /**@var Connection $db*/
        $db = Instance::ensure($db, Connection::class);
        $transaction = $db->beginTransaction();
        try{
            $result = $this->$method(...$args);
            $transaction->commit();
            return $result;
        }catch (\Throwable $e){
            $transaction->rollBack();
            throw $e;
        }
    }
}
