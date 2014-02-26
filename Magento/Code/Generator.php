<?php
/**
 * {license_notice}
 *
 * @category    Magento
 * @package     Magento_Code
 * @copyright   {copyright}
 * @license     {license_link}
 */

namespace Magento\Code;

class Generator
{
    const GENERATION_SUCCESS = 'success';
    const GENERATION_ERROR = 'error';
    const GENERATION_SKIP = 'skip';

    /**
     * @var \Magento\Code\Generator\EntityAbstract
     */
    protected $_generator;

    /**
     * @var \Magento\Autoload\IncludePath
     */
    protected $_autoloader;

    /**
     * @var \Magento\Code\Generator\Io
     */
    protected $_ioObject;

    /**
     * @var array
     */
    protected $_generatedEntities = array(
        \Magento\Code\Generator\Factory::ENTITY_TYPE,
        \Magento\Code\Generator\Proxy::ENTITY_TYPE,
        \Magento\Code\Generator\Interceptor::ENTITY_TYPE,
    );

    /**
     * @param Generator\EntityAbstract $generator
     * @param \Magento\Autoload\IncludePath $autoloader
     * @param Generator\Io $ioObject
     * @param \Magento\Filesystem $filesystem
     */
    public function __construct(
        \Magento\Code\Generator\EntityAbstract $generator = null,
        \Magento\Autoload\IncludePath $autoloader = null,
        \Magento\Code\Generator\Io $ioObject = null,
        \Magento\Filesystem $filesystem = null
    ) {
        //todo: remove $filesystem from constructor
        $this->_generator   = $generator;
        $this->_autoloader  = $autoloader ? : new \Magento\Autoload\IncludePath();
        $this->_ioObject    = $ioObject ? : new \Magento\Code\Generator\Io(
            new \Magento\Filesystem\Driver\File(), $this->_autoloader);
    }

    /**
     * @return array
     */
    public function getGeneratedEntities()
    {
        return $this->_generatedEntities;
    }

    /**
     * @param string $className
     * @return string const
     * @throws \Magento\Exception
     */
    public function generateClass($className)
    {
        // check if source class a generated entity
        $entity = null;
        $entityName = null;
        foreach ($this->_generatedEntities as $entityType) {
            $entitySuffix = ucfirst($entityType);
            // if $className string ends on $entitySuffix substring
            if (strrpos($className, $entitySuffix) === strlen($className) - strlen($entitySuffix)) {
                $entity = $entityType;
                $entityName = rtrim(substr($className, 0, -1 * strlen($entitySuffix)),
                    \Magento\Autoload\IncludePath::NS_SEPARATOR);
                break;
            }
        }
        if (!$entity || !$entityName) {
            return self::GENERATION_ERROR;
        }

        // check if file already exists
        $autoloader = $this->_autoloader;
        if ($autoloader::getFile($className)) {
            return self::GENERATION_SKIP;
        }

        // generate class file
        $this->_initGenerator($entity, $entityName, $className);
        if (!$this->_generator->generate()) {
            $errors = $this->_generator->getErrors();
            throw new \Magento\Exception(implode(' ', $errors));
        }

        // remove generator
        $this->_generator = null;

        return self::GENERATION_SUCCESS;
    }

    /**
     * Get generator by entity type
     *
     * @param string $entity
     * @param string $sourceClassName
     * @param string $resultClassName
     * @return \Magento\Code\Generator\EntityAbstract|\Magento\Code\Generator\Factory|\Magento\Code\Generator\Proxy
     * @throws \InvalidArgumentException
     */
    protected function _initGenerator($entity, $sourceClassName, $resultClassName)
    {
        if (!$this->_generator) {
            switch ($entity) {
                case \Magento\Code\Generator\Factory::ENTITY_TYPE:
                    $this->_generator = new \Magento\Code\Generator\Factory($sourceClassName, $resultClassName,
                        $this->_ioObject
                    );
                    break;
                case \Magento\Code\Generator\Proxy::ENTITY_TYPE:
                    $this->_generator = new \Magento\Code\Generator\Proxy($sourceClassName, $resultClassName,
                        $this->_ioObject
                    );
                    break;
                case \Magento\Code\Generator\Interceptor::ENTITY_TYPE:
                    $this->_generator = new \Magento\Code\Generator\Interceptor($sourceClassName, $resultClassName,
                        $this->_ioObject
                    );
                    break;
                default:
                    throw new \InvalidArgumentException('Unknown generation entity.');
                    break;
            }
        } else {
            $this->_generator->init($sourceClassName, $resultClassName);
        }

        return $this->_generator;
    }
}