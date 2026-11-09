<?php

class modX
{
    const LOG_LEVEL_ERROR = 3;

    public $context;
    public $error;
    public $user;
    public $jevixInstalled = true;
    public $logs = array();
    public $request;
    public $service;

    public function __construct()
    {
        $this->context = (object)array('key' => 'web');
        $this->error = new class {
            public function reset()
            {
            }
        };
        $this->request = new class {
            public function getClientIp()
            {
                return array('ip' => '127.0.0.1');
            }
        };
        $this->user = new class {
            public $id = 0;

            public function get($key)
            {
                return false;
            }

            public function hasSessionContext($context)
            {
                return false;
            }

            public function isAuthenticated($context)
            {
                return false;
            }
        };
    }

    public function getOption($key, $options = null, $default = null)
    {
        return $default;
    }

    public function getService($name, $class = '', $path = '')
    {
        return $this->service;
    }

    public function getCount($class, $criteria = null)
    {
        return $class === 'TicketThread' ? 1 : 0;
    }

    public function getFieldMeta($class)
    {
        return array_fill_keys(array(
            'createdby',
            'createdon',
            'deleted',
            'deletedby',
            'deletedon',
            'editedby',
            'editedon',
            'email',
            'ip',
            'name',
            'parent',
            'properties',
            'published',
            'raw',
            'text',
            'thread',
        ), true);
    }

    public function getObject($class, $criteria = null)
    {
        if ($class === 'modSnippet') {
            return $this->jevixInstalled ? (object)array('name' => 'Jevix') : null;
        }
        if ($class === 'TicketThread') {
            return (object)array('id' => 1);
        }

        return null;
    }

    public function hasPermission($permission)
    {
        return true;
    }

    public function lexicon($key, $placeholders = array())
    {
        return $key;
    }

    public function log($level, $message)
    {
        $this->logs[] = array($level, $message);
    }

    public function stripTags($value)
    {
        return strip_tags((string)$value);
    }
}

class modObjectCreateProcessor
{
    public $modx;
    public $object;
    public $properties = array();

    public function addFieldError($field, $message)
    {
    }

    public function beforeSet()
    {
        return true;
    }

    public function beforeSave()
    {
        return true;
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function getProperty($key, $default = null)
    {
        return array_key_exists($key, $this->properties) ? $this->properties[$key] : $default;
    }

    public function process()
    {
        if (method_exists($this, 'checkPermissions') && !$this->checkPermissions()) {
            return false;
        }

        $beforeSet = $this->beforeSet();
        if ($beforeSet !== true) {
            return $beforeSet;
        }

        $this->object->fromArray($this->properties);
        $beforeSave = $this->beforeSave();
        if ($beforeSave !== true) {
            return $beforeSave;
        }

        $this->object->save();

        return true;
    }

    public function setProperties(array $properties)
    {
        $this->properties = array_merge($this->properties, $properties);
    }

    public function setProperty($key, $value)
    {
        $this->properties[$key] = $value;
    }

    public function unsetProperty($key)
    {
        unset($this->properties[$key]);
    }
}

class modObjectUpdateProcessor extends modObjectCreateProcessor
{
}

class CommentTestObject
{
    public $savedValues = array();
    public $saveCalls = 0;
    public $values = array();

    public function fromArray(array $values)
    {
        $this->values = array_merge($this->values, $values);
    }

    public function get($key)
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : null;
    }

    public function getOne($alias)
    {
        return null;
    }

    public function save()
    {
        $this->saveCalls++;
        $this->savedValues = $this->values;

        return true;
    }
}

require_once __DIR__ . '/../core/components/tickets/model/tickets/tickets.class.php';
require_once __DIR__ . '/../core/components/tickets/processors/web/comment/create.class.php';
require_once __DIR__ . '/../core/components/tickets/processors/web/comment/update.class.php';

class CommentTestTickets extends Tickets
{
    public $capturedAction;
    public $capturedData;
    public $config;
    public $jevixCalls = 0;

    public function __construct(modX &$modx, array $config = array())
    {
        $this->modx =& $modx;
        $this->config = array_merge(array(
            'allowGuest' => false,
            'allowGuestEdit' => false,
            'autoPublish' => false,
            'autoPublishGuest' => false,
            'enableCaptcha' => false,
            'json_response' => false,
            'requiredFields' => '',
            'resource' => 1,
            'validate' => false,
        ), $config);
    }

    public function Jevix($text = null, $setName = 'Ticket', $replaceTags = true)
    {
        $this->jevixCalls++;

        return '<p>Clean</p>';
    }

    public function runProcessor($action = '', $data = array())
    {
        $this->capturedAction = $action;
        $this->capturedData = $data;

        return new class {
            public function isError()
            {
                return false;
            }

            public function getObject()
            {
                return array(
                    'createdby' => 0,
                    'published' => false,
                );
            }
        };
    }
}

function demand($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function makeProcessor($class, modX $modx, array $properties)
{
    $processor = new $class();
    $processor->modx = $modx;
    $processor->object = new CommentTestObject();
    $processor->properties = $properties;

    return $processor;
}

$modx = new modX();
$tickets = new CommentTestTickets($modx);
$modx->service = $tickets;
$raw = '<p>Raw</p><script>alert(1)</script>';

$processorProperties = array(
    'allowGuest' => true,
    'allowGuestEdit' => true,
    'email' => 'guest@example.test',
    'name' => 'Guest',
    'parent' => 0,
    'published' => true,
    'raw' => 'TAMPERED',
    'requiredFields' => 'name,email',
    'text' => $raw,
    'thread' => 'resource-1',
);

foreach (array('TicketCommentCreateProcessor', 'TicketCommentUpdateProcessor') as $processorClass) {
    $processor = makeProcessor($processorClass, $modx, $processorProperties);
    if ($processorClass === 'TicketCommentUpdateProcessor') {
        $processor->object->values = array(
            'createdby' => 0,
            'createdon' => date('Y-m-d H:i:s'),
            'deleted' => false,
            'ip' => '127.0.0.1',
            'published' => true,
        );
    }

    demand($processor->process() === true, $processorClass . ' rejected a valid comment');
    demand($processor->object->saveCalls === 1, $processorClass . ' did not save the comment');
    demand($processor->object->savedValues['raw'] === $raw, $processorClass . ' accepted a client-controlled raw value');
    demand($processor->object->savedValues['text'] === '<p>Clean</p>', $processorClass . ' did not save the sanitized text');
}

demand($tickets->jevixCalls === 2, 'Each processor must sanitize exactly once');

$modx->jevixInstalled = false;
foreach (array('TicketCommentCreateProcessor', 'TicketCommentUpdateProcessor') as $processorClass) {
    $processor = makeProcessor($processorClass, $modx, $processorProperties);
    if ($processorClass === 'TicketCommentUpdateProcessor') {
        $processor->object->values = array(
            'createdby' => 0,
            'createdon' => date('Y-m-d H:i:s'),
            'deleted' => false,
            'ip' => '127.0.0.1',
            'published' => true,
            'raw' => 'previous raw',
            'text' => 'previous text',
        );
        $processor->object->savedValues = $processor->object->values;
    }
    $persistedBefore = $processor->object->savedValues;

    demand($processor->process() === 'err_no_jevix', $processorClass . ' did not fail closed without Jevix');
    demand($processor->object->saveCalls === 0, $processorClass . ' saved the comment without Jevix');
    demand($processor->object->savedValues === $persistedBefore, $processorClass . ' changed persisted data without Jevix');
}

demand($tickets->jevixCalls === 2, 'Jevix must not run after the availability check fails');

$modx->jevixInstalled = true;
$tickets->saveComment(array(
    'id' => 1,
    'parent' => 0,
    'raw' => 'TAMPERED',
    'text' => $raw,
));

demand($tickets->capturedAction === 'web/comment/update', 'saveComment selected the wrong processor');
demand($tickets->capturedData['text'] === $raw, 'saveComment did not pass the original text to the processor');
demand(!array_key_exists('raw', $tickets->capturedData), 'saveComment forwarded a client-controlled raw value');
demand($tickets->jevixCalls === 2, 'saveComment sanitized text before the processor');

echo "COMMENT_JEVIX_CONTRACT_OK\n";
