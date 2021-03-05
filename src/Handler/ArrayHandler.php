<?php


namespace Copper\Handler;


use Copper\Entity\AbstractEntity;

class ArrayHandler
{
    /**
     * @param mixed $value
     * @param array $valueList
     * @param array $outputList
     *
     * @return mixed|null
     */
    public static function switch($value, array $valueList, array $outputList)
    {
        $output = null;

        foreach ($valueList as $k => $val) {
            if ($value === $val)
                $output = $outputList[$k];
        }

        return $output;
    }

    /**
     * @param array|object[]|AbstractEntity[] $arrayA
     * @param array|object[]|AbstractEntity[] $arrayB
     * @param bool $reindex
     *
     * @return array|AbstractEntity[]|object[]
     */
    public static function merge_uniqueValues(array $arrayA, array $arrayB, $reindex = false)
    {
        return self::merge($arrayA, $arrayB, true, $reindex);
    }

    /**
     * @param array|object[]|AbstractEntity[] $arrayA
     * @param array|object[]|AbstractEntity[] $arrayB
     * @param bool $uniqueValues
     *
     * @return array|AbstractEntity[]|object[]
     */
    public static function merge_reindexKeys(array $arrayA, array $arrayB, $uniqueValues = false)
    {
        return self::merge($arrayA, $arrayB, $uniqueValues, true);
    }

    /**
     * @param array|object[]|AbstractEntity[] $arrayA
     * @param array|object[]|AbstractEntity[] $arrayB
     * @param bool $uniqueValues
     * @param bool $reindexKeys
     *
     * @return array|object[]|AbstractEntity[]
     */
    public static function merge(array $arrayA, array $arrayB, $uniqueValues = false, $reindexKeys = false)
    {
        $res = array_merge($arrayA, $arrayB);

        if ($uniqueValues)
            $res = array_unique($res);

        if ($reindexKeys)
            $res = array_values($res);

        return $res;
    }

    /**
     * @param array $array
     * @param mixed $value
     * @param bool $strict
     *
     * @return mixed|null
     */
    public static function hasValue(array $array, $value, $strict = true)
    {
        $key = array_search($value, $array, $strict);

        return ($key !== false);
    }

    /**
     * @param array $array
     *
     * @return mixed
     */
    public static function lastValue(array $array)
    {
        $val = end($array);

        reset($array);

        return $val;
    }

    /**
     * @param array $array
     *
     * @return int|string|null
     */
    public static function lastKey(array $array)
    {
        end($array);

        $key = key($array);

        reset($array);

        return $key;
    }

    /**
     * @param array|object[]|AbstractEntity[] $array
     * @param array $filter
     *
     * @return array
     */
    public static function assocDelete(array $array, array $filter)
    {
        $newArray = [];

        foreach ($array as $key => $item) {
            if (self::assocMatch($item, $filter) === false)
                $newArray[] = $item;
        }

        return $newArray;
    }

    /**
     * @param array|object[]|AbstractEntity[] $array
     * @param string $key
     *
     * @return array
     */
    public static function assocValueList(array $array, string $key)
    {
        $list = [];

        foreach ($array as $k => $item) {
            if (is_array($item))
                $list[] = $item[$key];
            else
                $list[] = $item->$key;
        }

        return $list;
    }

    /**
     * @param array|object|AbstractEntity $item
     * @param array $filter
     *
     * @return bool
     */
    public static function assocMatch($item, array $filter)
    {
        $matched = true;

        $itemIsObject = true;
        if (is_array($item))
            $itemIsObject = false;

        foreach ($filter as $pairKey => $pairValue) {
            if ($itemIsObject === false) {
                if (is_array($pairValue) === false && $item[$pairKey] != $pairValue)
                    $matched = false;
                elseif (is_array($pairValue) && ArrayHandler::hasValue($pairValue, $item[$pairKey]) === false)
                    $matched = false;
            } else {
                if (is_array($pairValue) === false && $item->$pairKey != $pairValue)
                    $matched = false;
                elseif (is_array($pairValue) && ArrayHandler::hasValue($pairValue, $item->$pairKey) === false)
                    $matched = false;
            }
        }

        return $matched;
    }

    /**
     * @param array|object[] $array
     * @param array $filter - Key->Value pairs
     *
     * @return array
     */
    public static function assocFind(array $array, array $filter)
    {
        $list = [];

        foreach ($array as $k => $item) {
            if (self::assocMatch($item, $filter))
                $list[] = $item;
        }

        return $list;
    }

    /**
     * @param array $array
     * @param string $key
     * @param bool $sortASC
     *
     * @return array
     */
    public static function assocSort(array $array, string $key, $sortASC = true)
    {
        $col = array_column($array, $key);

        array_multisort($col, ($sortASC) ? SORT_ASC : SORT_DESC, $array);

        return $array;
    }

    /**
     * Clean array of empty & null values
     *
     * @param array $array
     * @param bool $delNull - Deletes keys with value === null
     * @param bool $delEmptyStr - Deletes keys with value === ''
     * @param bool $delEmptyArray - Deletes keys with value === []
     * @param bool $isAssoc - Is associative array ? (preserve key names)
     *
     * @return array
     */
    public static function clean(array $array, bool $isAssoc = false, bool $delNull = true, bool $delEmptyStr = false, bool $delEmptyArray = false)
    {
        $cleanArray = [];

        foreach ($array as $key => $value) {
            if ($value === null && $delNull === true
                || is_string($value) && trim($value) === '' && $delEmptyStr
                || is_array($value) && count($value) === 0 && $delEmptyArray)
                continue;

            if ($isAssoc === true)
                $cleanArray[$key] = $value;
            else
                $cleanArray[] = $value;
        }

        return $cleanArray;
    }
}