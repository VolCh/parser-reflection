<?php
namespace Go\ParserReflection;

use Go\ParserReflection\Stub\ClassWithProperties;

class ReflectionPropertyTest extends AbstractTestCase
{
    /**
     * Class to test
     *
     * @var string
     */
    protected static $reflectionClassToTest = \ReflectionProperty::class;

    /**
     * Class to load
     *
     * @var string
     */
    protected static $defaultClassToLoad = ClassWithProperties::class;

    /**
     * Performs method-by-method comparison with original reflection
     *
     * @dataProvider methodCaseProvider
     *
     * @param ReflectionClass $parsedClass Parsed class
     * @param string $getterName Name of the reflection method to test
     */
    public function testReflectionMethodParity(
        ReflectionClass $parsedClass,
        $getterName
    ) {
        $className = $parsedClass->getName();
        $refClass  = new \ReflectionClass($className);

        $expectedValue = $refClass->{$getterName}();
        $actualValue   = $parsedClass->{$getterName}();
        $this->assertSame(
            $expectedValue,
            $actualValue,
            "{$getterName}() for class {$className} should be equal"
        );
    }

    /**
     * Performs property-by-property comparison with original reflection
     *
     * @dataProvider propertyCaseProvider
     *
     * @param \ReflectionMethod $refMethod Original reflection method
     * @param ReflectionMethod $parsedMethod Parsed reflection method
     * @param string $propertyName
     */
    public function testReflectionPropertyParity(
        \ReflectionProperty $refMethod,
        ReflectionProperty $parsedMethod,
        $propertyName
    ) {
        $this->assertSame(
            $refMethod->{$propertyName},
            $parsedMethod->{$propertyName},
            "\${$propertyName} for class {$refMethod->class} should be equal"
        );
    }

    /**
     * Provides full test-case list in the form [ParsedClass, getter name to check]
     *
     * @return array
     */
    public function methodCaseProvider()
    {
        return $this->caseProvider($this->getGettersToCheck());
    }

    /**
     * Provides full test-case list in the form [ParsedClass, property name to check]
     *
     * @return array
     */
    public function propertyCaseProvider()
    {
        return $this->caseProvider($this->getPropertiesToCheck());
    }

    public function testSetAccessibleMethod()
    {
        $parsedProperty = $this->parsedRefClass->getProperty('protectedStaticProperty');
        $parsedProperty->setAccessible(true);

        $value = $parsedProperty->getValue();
        $this->assertSame('foo', $value);
    }

    public function testGetSetValueForObjectMethods()
    {
        $parsedProperty = $this->parsedRefClass->getProperty('protectedProperty');
        $parsedProperty->setAccessible(true);

        $className = $this->parsedRefClass->getName();
        $obj       = new $className;

        $value = $parsedProperty->getValue($obj);
        $this->assertSame('a', $value);

        $parsedProperty->setValue($obj, 43);
        $value = $parsedProperty->getValue($obj);
        $this->assertSame(43, $value);
    }

    public function testCompatibilityWithOriginalConstructor()
    {
        $parsedRefProperty = new ReflectionProperty($this->parsedRefClass->getName(), 'publicStaticProperty');
        $originalValue     = $parsedRefProperty->getValue();

        $this->assertSame(M_PI, $originalValue);
    }

    public function testDebugInfoMethod()
    {
        $parsedRefProperty   = $this->parsedRefClass->getProperty('publicStaticProperty');
        $originalRefProperty = new \ReflectionProperty($this->parsedRefClass->getName(), 'publicStaticProperty');
        $expectedValue     = (array) $originalRefProperty;
        $this->assertSame($expectedValue, $parsedRefProperty->___debugInfo());
    }

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     *
     * @return array
     */
    protected function getGettersToCheck()
    {
        $allNameGetters = [
            'isDefault', 'getName', 'getModifiers', 'getDocComment',
            'isPrivate', 'isProtected', 'isPublic', 'isStatic', '__toString'
        ];

        return $allNameGetters;
    }

    /**
     * Returns list of ReflectionProperty that be checked
     *
     * @return array
     */
    protected function getPropertiesToCheck()
    {
        $names = [
            'name',
        ];

        return $names;
    }

    /**
     * @param string[] $membersToCheck
     * @return array
     */
    protected function caseProvider($membersToCheck)
    {
        $testCases = [];
        $files = $this->getFilesToAnalyze();
        foreach ($files as $fileList) {
            foreach ($fileList as $fileName) {
                $fileName = stream_resolve_include_path($fileName);
                $fileNode = ReflectionEngine::parseFile($fileName);

                $reflectionFile = new ReflectionFile($fileName, $fileNode);
                include_once $fileName;
                foreach ($reflectionFile->getFileNamespaces() as $fileNamespace) {
                    foreach ($fileNamespace->getClasses() as $refClass) {
                        $qcn = ltrim($refClass->getName(), '\\'); // workaround for #80
                        $fqcn = '\\' . $qcn;

                        foreach ($refClass->getProperties() as $refProperty) {
                            if (!$refProperty instanceof ReflectionMethod) {
                                continue;
                            }
                            $propertyName = $refProperty->getName();

                            $properties = [
                                $qcn . '->$' . $propertyName => [$refProperty, new \ReflectionProperty($qcn, $propertyName)],
                                $fqcn . '->$' . $propertyName => [new ReflectionProperty($fqcn, $propertyName, $refProperty->getNode()), new \ReflectionProperty($qcn, $propertyName)],
                            ];
                            foreach ($properties as $caseName => list($parsedClass, $originalClass)) {
                                foreach ($membersToCheck as $memberToCheck) {
                                    $testCases[$caseName . ', ' . $memberToCheck] = [
                                        $originalClass,
                                        $parsedClass,
                                        $memberToCheck,
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $testCases;
    }
}
