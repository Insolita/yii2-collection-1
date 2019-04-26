<?php

namespace yii\collection;

use Closure;
use Generator;
use Iterator;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\helpers\ArrayHelper;
use function is_iterable;

class BaseGeneratorCollection extends Component implements \IteratorAggregate
{
    /** @var Iterator|Generator */
    protected $iterator;

    public function __construct($data = [], $config = [])
    {
        parent::__construct($config);

        if (is_scalar($data) || $data instanceof \stdClass || $data === null) {
            $data = (array)$data;
        }
        if (is_array($data)) {
            $this->iterator = new \ArrayIterator($data);
        } elseif ($data instanceof \IteratorAggregate) {
            $this->iterator = $data->getIterator();
        } elseif ($data instanceof Iterator) {
            $this->iterator = $data;
        } elseif (\is_iterable($data)) {
            $this->iterator = $this->generator($data);
        } else {
            throw new InvalidArgumentException('value shoud be an array or iterable');
        }
    }

    public function getIterator()
    {
        return $this->iterator;
    }

    /**
     * @param iterable $data
     * @return \Generator
     */
    public function generator($data)
    {
        foreach ($data as $key => $value) {
            yield $key => $value;
        }
    }

    public function getData()
    {
        return iterator_to_array($this->iterator);
    }

    /**
     * @return bool a value indicating whether the collection is empty.
     */
    public function isEmpty()
    {
        return $this->count() === 0;
    }

    public function map($callable)
    {
        return $this->chain(function(Iterator $data) use ($callable) {
            foreach ($data as $key => $value) {
                yield $key => $callable($value, $key);
            }
        });
    }

    public function flatMap($callable)
    {
        return $this->map($callable)->collapse();

    }

    public function filter($callable)
    {
        return $this->chain(function(Iterator $data) use ($callable) {
            foreach ($data as $key => $value) {
                if ($callable($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    public function reduce($callable, $initial = null)
    {
        foreach ($this->iterator as $key => $value) {
            $initial = $callable($initial, $value, $key);
        }
        return $initial;
    }

    public function keys()
    {
        return $this->chain(function(Iterator $data) {
            foreach ($data as $key => $value) {
                yield $key;
            }
        });
    }

    public function values()
    {
        return $this->chain(function(Iterator $data) {
            foreach ($data as $key => $value) {
                yield $value;
            }
        });
    }

    public function each($callable, $breakOnFalse = false)
    {
        return $this->chain(function(Iterator $data) use (&$callable, $breakOnFalse) {
            $breaked = false;
            foreach ($data as $key => $value) {
                $result = $callable($value, $key);
                if ($breakOnFalse === true && $result === false) {
                    $breaked = true;
                }
                yield $key => $value;
                if ($breaked) {
                    break;
                }
            }
        });
    }

    public function indexBy($index)
    {
        return $this->chain(function(Iterator $data) use ($index) {
            foreach ($data as $key => $value) {
                yield ArrayHelper::getValue($value, $index) => $value;
            }
        });
    }

    public function column($name, $keepKeys = false)
    {
        return $this->chain(function(Iterator $data) use ($name, $keepKeys) {
            foreach ($data as $key => $value) {
                if ($keepKeys === true) {
                    yield $key => ArrayHelper::getValue($value, $name);
                } else {
                    yield ArrayHelper::getValue($value, $name);
                }
            }
        });
    }

    public function flip()
    {
        return $this->chain(function(Iterator $data) {
            foreach ($data as $key => $value) {
                yield $value => $key;
            }
        });
    }

    /**
     * Replace a specific item in the collection with another one.
     * The original collection will not be changed, a new collection with modified data is returned.
     * @param mixed $item        the item to search for.
     * @param mixed $replacement the replacement to insert instead of the item.
     * @param bool  $strict      whether comparison should be compared strict (`===`) or not (`==`).
     *                           Defaults to `false`.
     * @return static a new collection containing the new set of items.
     * @see map()
     */
    public function replace($item, $replacement, $strict = false)
    {
        return $this->map(function($i) use ($item, $replacement, $strict) {
            if ($strict ? $i === $item : $i == $item) {
                return $replacement;
            }
            return $i;
        });
    }

    /**
     * Remove a specific item from the collection.
     * The original collection will not be changed, a new collection with modified data is returned.
     * @param mixed|Closure $item   the item to search for. You may also pass a closure that returns a boolean.
     *                              The closure will be called on each item and in case it returns `true`, the item
     *                              will be removed. In case a closure is passed, `$strict` parameter has no effect.
     * @param bool          $strict whether comparison should be compared strict (`===`) or not (`==`).
     *                              Defaults to `false`.
     * @return static a new collection containing the filtered items.
     * @see filter()
     */
    public function remove($item, $strict = false)
    {
        if ($item instanceof Closure) {
            $fun = function($i) use ($item) { return !$item($i); };
        } elseif ($strict) {
            $fun = function($i) use ($item) { return $i !== $item; };
        } else {
            $fun = function($i) use ($item) { return $i != $item; };
        }
        return $this->filter($fun);
    }

    /**
     * Convert collection data by selecting a new key and a new value for each item.
     * Builds a map (key-value pairs) from a multidimensional array or an array of objects.
     * The `$from` and `$to` parameters specify the key names or property names to set up the map.
     * The original collection will not be changed, a new collection with newly mapped data is returned.
     * @param string|Closure $from the field of the item to use as the key of the created map.
     *                             This can be a closure that returns such a value.
     * @param string|Closure $to   the field of the item to use as the value of the created map.
     *                             This can be a closure that returns such a value.
     * @return static a new collection containing the mapped data.
     * @see ArrayHelper::map()
     */
    public function remap($from, $to)
    {
        return $this->chain(function(Iterator $data) use ($from, $to) {
            foreach ($data as $key => $value) {
                $key = ArrayHelper::getValue($value, $from);
                $value = ArrayHelper::getValue($value, $to);
                yield $key => $value;
            }
        });
    }

    /**
     * Alias to remap
     * @param $from
     * @param $to
     * @return \yii\collection\BaseGeneratorCollection
     */
    public function pluck($from, $to)
    {
        return $this->remap($from, $to);
    }

    /**
     * Check whether the collection contains a specific item.
     * @param mixed|Closure $item   the item to search for. You may also pass a closure that returns a boolean.
     *                              The closure will be called on each item and in case it returns `true`, the item
     *                              will be considered to be found. In case a closure is passed, `$strict` parameter
     *                              has no effect.
     * @param bool          $strict whether comparison should be compared strict (`===`) or not (`==`).
     *                              Defaults to `false`.
     * @return bool `true` if the collection contains at least one item that matches, `false` if not.
     */
    public function contains($item, $strict = false)
    {
        if ($item instanceof Closure) {
            foreach ($this->iterator as $i) {
                if ($item($i)) {
                    return true;
                }
            }
        } else {
            foreach ($this->iterator as $i) {
                if ($strict ? $i === $item : $i == $item) {
                    return true;
                }
            }
        }
        return false;
    }

    public function sum($field = null)
    {
        return $this->reduce(function($carry, $model) use ($field) {
            return $field !== null ? $carry + ArrayHelper::getValue($model, $field, 0)
                : $carry + $model;
        }, 0);
    }

    public function max($field = null)
    {
        return $this->reduce(function($carry, $model) use ($field) {
            $value = $field !== null ? ArrayHelper::getValue($model, $field, 0): $model;
            if ($carry === null) {
                return $value;
            }
            return $value > $carry ? $value : $carry;
        });
    }

    public function min($field = null)
    {
        return $this->reduce(function($carry, $model) use ($field) {
            $value = $field !== null ? ArrayHelper::getValue($model, $field, 0): $model;
            if ($carry === null) {
                return $value;
            }
            return $value < $carry ? $value : $carry;
        });
    }

    public function count()
    {
        return $this->reduce(function($carry) { return $carry + 1; }, 0);
    }

    public function collapse()
    {
        return $this->chain(function(Iterator $data) {
            foreach ($data as $key => $value) {
                if (is_iterable($value)) {
                    $collection = new static($value);
                    foreach ($collection->collapse()->getIterator() as $item) {
                        yield $item;
                    }
                } else {
                    yield $value;
                }
            }
        });
    }

    public function unique()
    {
        return $this->reduce(function($carry, $value) {
            if (!in_array($value, $carry, true)) {
                $carry[] = $value;
            }
            return $carry;
        }, []);
    }

    public function merge($collection)
    {
        if (!is_iterable($collection)) {
            throw new InvalidArgumentException('only iterable data can be appended');
        }
        return $this->chain(function(Iterator $data) use (&$collection) {
            yield from $data;
            yield from $collection;
        });
    }

    public function current()
    {
        return $this->iterator->current();
    }

    public function valid()
    {
        return $this->iterator->valid();
    }

    public function key()
    {
        return $this->iterator->key();
    }

    public function next()
    {
        $this->iterator->next();
    }

    public function rewind()
    {
        $this->iterator->rewind();
    }

    /**
     * @param callable $payloadGenerator
     * @return static
     */
    private function chain(callable $payloadGenerator)
    {
        return new static($payloadGenerator($this->iterator, $this));
    }
}
