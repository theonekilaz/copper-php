<?php


namespace Copper\Component\DB;


use Copper\Handler\VarHandler;

class DBWhereEntry
{
    /** @var string|string[] */
    public $field;
    /** @var string|int|float|null */
    public $value;
    /** @var int */
    public $cond;
    /** @var int */
    public $chain;

    public function __construct($field, $value, int $cond, int $chain)
    {
        $this->field = $field;
        $this->value = $value;
        $this->cond = $cond;
        $this->chain = $chain;
    }

    public function formatField()
    {
        if (VarHandler::isArray($this->field) === false)
            return '`' . DBModel::formatFieldName($this->field) . '`';

        $formatted_field_list = [];

        foreach ($this->field as $field) {
            $formatted_field_list[] = '`' . DBModel::formatFieldName($field) . '`';
        }

        return $formatted_field_list;
    }

    public function formatValue()
    {
        $value = $this->value;

        if (is_bool($value) === true)
            $value = intval($value);

        if (VarHandler::isArray($value)) {
            $value_list = [];

            foreach ($value as $key => $val) {
                if (is_object($val) && property_exists($val, 'id') && $val->id > 0)
                    $value_list[] = $val->id;
                else
                    $value_list[] = $val;
            }

            $value = $value_list;
        }

        if (in_array($this->cond, [
            DBWhere::BETWEEN,
            DBWhere::BETWEEN_INCLUDE,
            DBWhere::NOT_BETWEEN,
            DBWhere::NOT_BETWEEN_INCLUDE
        ])) {
            $value[0] = DBModel::formatNumber($value[0]);
            $value[1] = DBModel::formatNumber($value[1]);
        }

        return $value;
    }
}