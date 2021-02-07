<?php


namespace Copper\Component\CP\DB;


use Copper\ArrayReader;
use Copper\Component\DB\DBModelField;
use Copper\FunctionResponse;
use Copper\Kernel;

class DBGenerator
{
    const T = '    ';
    const T2 = self::T . self::T;

    /**
     * @param $jsonContent
     * @return FunctionResponse
     */
    public static function run($jsonContent)
    {
        $response = new FunctionResponse();

        $content = json_decode($jsonContent, true);

        $resource = $content['resource'] ?? false;
        $table = $content['table'] ?? false;
        $entity = $content['entity'] ?? false;
        $model = $content['model'] ?? false;
        $service = $content['service'] ?? false;
        $seed = $content['seed'] ?? false;
        $controller = $content['controller'] ?? false;

        $fields = self::formatFields(ArrayReader::clean($content['fields'])) ?? false;

        $use_state_fields = $content['use_state_fields'] ?? false;

        $resource_override = $content['resource_override'] ?? false;
        $model_override = $content['model_override'] ?? false;
        $entity_override = $content['entity_override'] ?? false;
        $service_override = $content['service_override'] ?? false;
        $seed_override = $content['seed_override'] ?? false;
        $controller_override = $content['controller_override'] ?? false;

        $create_resource = $content['create_resource'] ?? false;
        $create_entity = $content['create_entity'] ?? false;
        $create_model = $content['create_model'] ?? false;
        $create_service = $content['create_service'] ?? false;
        $create_seed = $content['create_seed'] ?? false;
        $create_controller = $content['create_controller'] ?? false;

        if ($table === false || $fields === false)
            return $response->fail('Please provide all information. Table, Fields');

        $responses = [];

        $responses['entity'] = self::createEntity($create_entity, $entity, $fields, $use_state_fields, $entity_override);
        $responses['model'] = self::createModel($table, $entity, $create_model, $model, $fields, $use_state_fields, $model_override);
        $responses['service'] = self::createService($create_service, $model, $entity, $service, $service_override);
        $responses['seed'] = self::createSeed($create_seed, $model, $entity, $seed, $seed_override);
        $responses['controller'] = self::createController($create_controller, $resource, $model, $entity, $service, $controller, $controller_override, $use_state_fields);

        $is_relation = false;
        if ($create_service === false && $create_controller === false && $create_entity === false)
            $is_relation = true;

        if ($create_seed === false)
            $seed = false;

        $responses['resource'] = self::createResource($create_resource, $model, $entity, $service, $controller, $seed, $resource, $resource_override);

        return $response->ok('success', $responses);
    }

    private static function formatFields($fields)
    {
        foreach ($fields as $key => $field) {
            foreach ($field as $fKey => $fVal) {
                if (is_array($fVal))
                    $fields[$key][$fKey] = join($fVal, ',');
            }
        }

        return $fields;
    }

    private static function filePath($name, $type)
    {
        $folder = Kernel::getProjectPath() . '/src/' . $type;

        if (file_exists($folder) === false)
            mkdir($folder);

        return $folder . '/' . $name . '.php';
    }

    private static function createResource($create, $model, $entity, $service, $controller, $seed, $name, $override)
    {
        $response = new FunctionResponse();

        $pathGroup = strtolower($name);

        $filePath = self::filePath($name, 'Resource');

        if ($create === false)
            return $response->ok('Skipped');

        if (file_exists($filePath) && $override === false)
            return $response->fail($name . ' is not created. Override is set to false.');

        $seedClass = ($seed !== false) ? "use App\Seed\\$seed;" : '';
        $seedFunc = ($seed !== false) ? "
    static function getSeedClassName()
    {
        return $seed::class;
    }" : '';

        $content = "<?php

namespace App\Resource;

use App\Controller\\$controller;
use App\Entity\\$entity;
use App\Model\\$model;
$seedClass
use App\Service\\$service;
use Copper\Resource\AbstractResource;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class $name extends AbstractResource
{
    static function getEntityClassName()
    {
        return $entity::class;
    }

    static function getControllerClassName()
    {
        return $controller::class;
    }

    static function getModelClassName()
    {
        return $model::class;
    }

    static function getServiceClassName()
    {
        return $service::class;
    }
    $seedFunc
    const PATH_GROUP = '$pathGroup';

    const GET_LIST = 'getList@/' . self::PATH_GROUP . '/list';
    const GET_EDIT = 'getEdit@/' . self::PATH_GROUP . '/edit/{id}';
    const POST_UPDATE = 'postUpdate@/' . self::PATH_GROUP . '/update/{id}';
    const GET_NEW = 'getNew@/' . self::PATH_GROUP . '/new';
    const POST_CREATE = 'postCreate@/' . self::PATH_GROUP . '/create';
    const POST_REMOVE = 'postRemove@/' . self::PATH_GROUP . '/remove/{id}';
    const POST_UNDO_REMOVE = 'postUndoRemove@/' . self::PATH_GROUP . '/remove/undo/{id}';

    public static function registerRoutes(RoutingConfigurator \$routes)
    {
        self::addRoute(\$routes, self::GET_LIST);
        self::addRoute(\$routes, self::GET_EDIT);
        self::addRoute(\$routes, self::POST_UPDATE);
        self::addRoute(\$routes, self::GET_NEW);
        self::addRoute(\$routes, self::POST_CREATE);
        self::addRoute(\$routes, self::POST_REMOVE);
        self::addRoute(\$routes, self::POST_UNDO_REMOVE);
    }
}";
        file_put_contents($filePath, $content);

        return $response->ok();
    }

    private static function createController($create, $resource, $model, $entity, $service, $name, $override, $use_state_fields)
    {
        $response = new FunctionResponse();

        $filePath = self::filePath($name, 'Controller');

        if ($create === false)
            return $response->ok('Skipped');

        if (file_exists($filePath) && $override === false)
            return $response->fail($name . ' is not created. Override is set to false.');

        $excluded_param = "[$model::ID]";

        if ($use_state_fields)
            $excluded_param = "[$model::ID, $model::CREATED_AT, $model::UPDATED_AT, $model::REMOVED_AT]";

        $content = "<?php

namespace App\\Controller;

use App\\Entity\\$entity;
use App\\Model\\$model;
use App\\Service\\$service;
use App\\Resource\\$resource;
use Copper\\Component\\DB\\DBModel;
use Copper\\Component\\DB\\DBOrder;
use Copper\\Controller\\AbstractController;

class $name extends AbstractController
{
    const EXCLUDED_UPDATE_PARAMS = $excluded_param;
    const EXCLUDED_CREATE_PARAMS = $excluded_param;

    const TEMPLATE_LIST = 'collection/list';
    const TEMPLATE_EDIT = 'collection/edit';
    const TEMPLATE_NEW = 'collection/new';

    /** @var $resource */
    public \$resource = $resource::class;

    /** @var $service */
    private \$service;
    /** @var $model */
    private \$model;
    /** @var $entity */
    private \$entity;

    public function init()
    {
        \$this->service = \$this->resource::getService();
        \$this->model = \$this->resource::getModel();
        \$this->entity = \$this->resource::getEntity();
    }

    public function getList()
    {
        \$limit = \$this->request->query->get('limit', 20);
        \$offset = \$this->request->query->get('offset', 0);
        \$order = \$this->request->query->get('order', DBOrder::ASC);
        \$order_by = \$this->request->query->get('order_by', DBModel::ID);
        \$show_removed = \$this->request->query->get('show_removed', false);

        \$dbOrder = new DBOrder(\$this->model, \$order_by, (strtoupper(\$order) === DBOrder::ASC));

        /** @var {$entity}[] \$list */
        \$list = \$this->service::getList(\$this->db, \$limit, \$offset, \$dbOrder, \$show_removed);

        return \$this->viewResponse(self::TEMPLATE_LIST, ['list' => \$list, 'resource' => \$this->resource]);
    }

    public function getEdit(\$id)
    {
        /** @var {$entity} \$entity */
        \$entity = \$this->service::get(\$this->db, \$id);

        return \$this->viewResponse(self::TEMPLATE_EDIT, ['entity' => \$entity, 'resource' => \$this->resource]);
    }

    public function postUpdate(\$id)
    {
        \$updateParams = \$this->requestParamsExcluding(self::EXCLUDED_UPDATE_PARAMS);

        \$validateResponse = \$this->validator->validateModel(\$updateParams, \$this->model);

        if (\$validateResponse->hasError()) {
            \$this->flashMessage->setError(\$validateResponse->msg);
        } else {
            \$updateResponse = \$this->service::update(\$this->db, \$id, \$updateParams);

            if (\$updateResponse->hasError())
                \$this->flashMessage->setError(\$updateResponse->msg);
        }

        return \$this->redirectToRoute(\$this->resource::GET_EDIT, ['id' => \$id]);
    }

    public function getNew()
    {
        return \$this->viewResponse(self::TEMPLATE_NEW, ['resource' => \$this->resource]);
    }

    public function postCreate()
    {
        \$createParams = \$this->requestParamsExcluding(self::EXCLUDED_CREATE_PARAMS);

        \$validateResponse = \$this->validator->validateModel(\$createParams, \$this->model);

        if (\$validateResponse->hasError()) {
            \$this->flashMessage->setError(\$validateResponse->msg);
        } else {
            \$createResponse = \$this->service::create(\$this->db, \$this->entity::fromArray(\$createParams));

            if (\$createResponse->hasError())
                \$this->flashMessage->setError(\$createResponse->msg);
        }

        return \$this->redirectToRoute(\$this->resource::GET_LIST);
    }

    public function postRemove(\$id)
    {
        \$removeResponse = \$this->service::remove(\$this->db, \$id);

        if (\$removeResponse->hasError())
            \$this->flashMessage->setError(\$removeResponse->msg);
        else {
            \$this->flashMessage->setSuccess('Entity #' . \$id . ' is successfully removed');
            \$this->flashMessage->set('undo_id', \$id);
        }

        return \$this->redirectToRoute(\$this->resource::GET_LIST);
    }
    
    public function postUndoRemove(\$id)
    {
        \$response = \$this->service::undoRemove(\$this->db, \$id);

        if (\$response->hasError())
            \$this->flashMessage->setError(\$response->msg);
        else
            \$this->flashMessage->setSuccess('Entity #' . \$id . ' is restored and disabled');

        return \$this->redirectToRoute(\$this->resource::GET_LIST);
    }

}";
        file_put_contents($filePath, $content);

        return $response->ok();
    }

    private static function createSeed($create, $model, $entity, $name, $override)
    {
        $response = new FunctionResponse();

        $filePath = self::filePath($name, 'Seed');

        if ($create === false)
            return $response->ok('Skipped');

        if (file_exists($filePath) && $override === false)
            return $response->fail($name . ' is not created. Override is set to false.');

        $var = strtolower($entity);

        $content = <<<XML
<?php

namespace App\Seed;

use App\Entity\\$entity;
use App\Model\\$model;
use Copper\Component\DB\DBSeed;

class $name extends DBSeed
{
    public function getModelClassName()
    {
        return $model::class;
    }

    public function setSeeds()
    {
        // \$entity = new $entity();
        // \$entity->enabled = true;
        // \$this->seed(\$entity);
    }
}
XML;
        file_put_contents($filePath, $content);

        return $response->ok();

    }

    private static function createService($create, $model, $entity, $name, $override)
    {
        $response = new FunctionResponse();

        $filePath = self::filePath($name, 'Service');

        if ($create === false)
            return $response->ok('Skipped');

        if (file_exists($filePath) && $override === false)
            return $response->fail($name . ' is not created. Override is set to false.');

        $useEntity = 'use App\Entity\\' . $entity;
        $useModel = 'use App\Model\\' . $model;

        $content = <<<XML
<?php


namespace App\Service;

$useEntity;
$useModel;
use Copper\Component\DB\DBCollectionService;

class $name extends DBCollectionService
{
    public static function getModelClassName()
    {
        return $model::class;
    }

    public static function getEntityClassName()
    {
        return $entity::class;
    }

}
XML;
        file_put_contents($filePath, $content);

        return $response->ok();
    }

    private static function createModel($table, $entity, $create, $name, $fields, $use_state_fields, $override)
    {
        $response = new FunctionResponse();

        $filePath = self::filePath($name, 'Model');

        if ($create === false)
            return $response->ok('Skipped');

        if (file_exists($filePath) && $override === false)
            return $response->fail($name . ' is not created. Override is set to false.');

        $constFields = '';
        $fieldSet = '';
        $stateFieldsFunc = ($use_state_fields)
            ? self::T2 . '// ------ State Fields ------' . "\r\n" . self::T2 . '$this->addStateFields();' : '';

        foreach ($fields as $fieldData) {
            $fName = $fieldData['name'];
            $fType = $fieldData['type'];
            $fLength = $fieldData['length'];
            $fDefault = $fieldData['default'];
            $fAttr = $fieldData['attr'];
            $fNull = $fieldData['null'];
            $fIndex = $fieldData['index'];
            $fAutoIncrement = $fieldData['auto_increment'];

            $field = new DBModelField($fName, $fType);

            if (in_array($fType, [DBModelField::DECIMAL, DBModelField::ENUM]) !== false) {
                $q = ($fType === DBModelField::DECIMAL) ? '' : "'";
                $fLength = "[$q" . join("$q, $q", explode(',', $fLength)) . "$q]";
            }

            $fNameUp = strtoupper($fName);

            $constFields .= self::T . "const $fNameUp = '$fName';\r\n";

            if ($fType === DBModelField::ENUM) {
                foreach (explode(',', $fieldData['length']) as $enumKey) {
                    $enumKeyUp = strtoupper($enumKey);
                    $constFields .= self::T . "const {$fNameUp}__$enumKeyUp = '$enumKey';\r\n";
                    $fLength = str_replace("'" . $enumKey . "'", "self::{$fNameUp}__$enumKeyUp", $fLength);
                }
                $fDefault = str_replace($fDefault, 'self::' . strtoupper($fName) . '__' . strtoupper($fDefault), $fDefault);
            }

            $fieldSetStr = self::T2 . '$this->' . "addField(self::$fNameUp, DBModelField::$fType";

            $fieldSetStr .= ($fLength !== false) ? ', ' . $fLength . ')' : ')';

            // if (strtolower($fName) === 'id' && $fIndex === DBModelField::INDEX_PRIMARY && strpos($fType, 'INT') !== false)
            //    $fAutoIncrement = false;

            if ($fIndex === DBModelField::INDEX_PRIMARY)
                $fieldSetStr .= '->primary()';
            elseif ($fIndex === DBModelField::INDEX_UNIQUE)
                $fieldSetStr .= '->unique()';

            if ($fAttr === DBModelField::ATTR_UNSIGNED)
                $fieldSetStr .= '->unsigned()';
//            elseif ($fAttr === DBModelField::ATTR_BINARY)
//                $fieldSetStr .= '->binary()';
//            elseif ($fAttr === DBModelField::ATTR_UNSIGNED_ZEROFILL)
//                $fieldSetStr .= '->unsignedZeroFill()';
            elseif ($fAttr === DBModelField::ATTR_ON_UPDATE_CURRENT_TIMESTAMP)
                $fieldSetStr .= '->currentTimestampOnUpdate()';

            if ($fAutoIncrement === true)
                $fieldSetStr .= '->autoIncrement()';

            if ($fDefault === DBModelField::DEFAULT_NULL)
                $fieldSetStr .= '->nullByDefault()';
            elseif ($fDefault === DBModelField::DEFAULT_CURRENT_TIMESTAMP)
                $fieldSetStr .= '->currentTimestampByDefault()';
            elseif ($fDefault !== DBModelField::DEFAULT_NONE) {
                if ($field->typeIsFloat() === false && $field->typeIsInteger() === false && $field->typeIsEnum() === false)
                    $fDefault = "'$fDefault'";

                $fieldSetStr .= "->default($fDefault)";
            }

            if ($fNull && $fDefault !== DBModelField::DEFAULT_NULL)
                $fieldSetStr .= "->null()";

            $fieldSet .= $fieldSetStr . ";\r\n";
        }

        $content = <<<XML
<?php

namespace App\Model;

use App\Entity\\$entity;
use Copper\Component\DB\DBModel;
use Copper\Component\DB\DBModelField;

class $name extends DBModel
{
$constFields
    public function getTableName()
    {
        return '$table';
    }
    
    public function getEntityClassName()
    {
        return $entity::class;
    }

    public function setFields()
    {
$fieldSet
$stateFieldsFunc
    }

}
XML;

        file_put_contents($filePath, $content);

        return $response->ok();
    }

    private static function createEntity($create, $name, $fields, $use_state_fields, $override)
    {
        $response = new FunctionResponse();

        $filePath = self::filePath($name, 'Entity');

        if ($create === false)
            return $response->ok('Skipped');

        if (file_exists($filePath) && $override === false)
            return $response->fail($name . ' is not created. Override is set to false.');

        $use_state_fields_trait = ($use_state_fields === true) ? "use EntityStateFields;\r\n" : "\r\n";
        $use_state_fields_trait_class = ($use_state_fields === true) ? "use Copper\Traits\EntityStateFields;\r\n" : "\r\n";

        $fields_content = '';

        foreach ($fields as $field) {
            $fName = $field['name'];
            $type = 'string';

            $field = new DBModelField($fName, $field['type']);

            if ($field->typeIsInteger())
                $type = 'integer';

            if ($field->typeIsFloat())
                $type = 'float';

            if ($field->typeIsBoolean())
                $type = 'boolean';

            $fields_content .= "    /** @var $type */\r\n    public $$fName;\r\n";
        }

        $content = <<<XML
<?php


namespace App\Entity;


use Copper\Entity\AbstractEntity;
$use_state_fields_trait_class
class $name extends AbstractEntity
{
    $use_state_fields_trait
$fields_content
}
XML;

        file_put_contents($filePath, $content);

        return $response->ok();
    }
}