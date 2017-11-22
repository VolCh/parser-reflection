<?php
namespace Go\ParserReflection;

class ReflectionMethodTest extends AbstractTestCase
{
    protected static $reflectionClassToTest = \ReflectionMethod::class;

    public function testGetClosureMethod()
    {
        $refMethod = $this->parsedRefClass->getMethod('funcWithDocAndBody');
        $closure   = $refMethod->getClosure(null);

        $this->assertInstanceOf(\Closure::class, $closure);
        $retValue = $closure();
        $this->assertEquals('hello', $retValue);
    }

    public function testInvokeMethod()
    {
        $refMethod = $this->parsedRefClass->getMethod('funcWithReturnArgs');
        $retValue  = $refMethod->invoke(null, 1, 2, 3);
        $this->assertEquals([1, 2, 3], $retValue);
    }

    public function testInvokeArgsMethod()
    {
        $refMethod = $this->parsedRefClass->getMethod('funcWithReturnArgs');
        $retValue  = $refMethod->invokeArgs(null, [1, 2, 3]);
        $this->assertEquals([1, 2, 3], $retValue);
    }

    public function testDebugInfoMethod()
    {
        $parsedRefMethod   = $this->parsedRefClass->getMethod('funcWithDocAndBody');
        $originalRefMethod = new \ReflectionMethod($this->parsedRefClass->getName(), 'funcWithDocAndBody');
        $expectedValue     = (array) $originalRefMethod;
        $this->assertSame($expectedValue, $parsedRefMethod->___debugInfo());
    }

    public function testSetAccessibleMethod()
    {
        $refMethod = $this->parsedRefClass->getMethod('protectedStaticFunc');
        $refMethod->setAccessible(true);
        $retValue = $refMethod->invokeArgs(null, []);
        $this->assertEquals(null, $retValue);
    }

    public function testGetPrototypeMethod()
    {
        $refMethod = $this->parsedRefClass->getMethod('prototypeMethod');
        $retValue  = $refMethod->invokeArgs(null, []);
        $this->assertEquals($this->parsedRefClass->getName(), $retValue);

        $prototype = $refMethod->getPrototype();
        $this->assertInstanceOf(\ReflectionMethod::class, $prototype);
        $prototype->setAccessible(true);
        $retValue  = $prototype->invokeArgs(null, []);
        $this->assertNotEquals($this->parsedRefClass->getName(), $retValue);
    }

    /**
     * Performs method-by-method comparison with original reflection
     *
     * @dataProvider methodCaseProvider
     *
     * @param \ReflectionMethod $refMethod Original reflection method
     * @param ReflectionMethod $parsedMethod Parsed reflection method
     * @param string                  $getterName Name of the reflection method to test
     */
    public function testReflectionMethodParity(
        \ReflectionMethod $refMethod,
        ReflectionMethod $parsedMethod,
        $getterName
    ) {
        $this->assertSame(
            $refMethod->{$getterName}(),
            $parsedMethod->{$getterName}(),
            "{$getterName}() for method {$refMethod->class}->{$getterName}() should be equal"
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
        \ReflectionMethod $refMethod,
        ReflectionMethod $parsedMethod,
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

    /**
     * Returns list of ReflectionMethod getters that be checked directly without additional arguments
     *
     * @return array
     */
    protected function getGettersToCheck()
    {
        $allNameGetters = [
            'getStartLine', 'getEndLine', 'getDocComment', 'getExtension', 'getExtensionName', 'getName',
            'getNamespaceName', 'getShortName', 'inNamespace', 'getStaticVariables', 'isClosure', 'isDeprecated',
            'isInternal', 'isUserDefined', 'isAbstract', 'isConstructor', 'isDestructor', 'isFinal', 'isPrivate',
            'isProtected', 'isPublic', 'isStatic', '__toString', 'getNumberOfParameters',
            'getNumberOfRequiredParameters', 'returnsReference', 'getClosureScopeClass', 'getClosureThis'
        ];

        if (PHP_VERSION_ID >= 50600) {
            $allNameGetters[] = 'isVariadic';
            $allNameGetters[] = 'isGenerator';
        }

        if (PHP_VERSION_ID >= 70000) {
            $allNameGetters[] = 'hasReturnType';
        }

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
            'class',
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

                        foreach ($refClass->getMethods() as $refMethod) {
                            if (!$refMethod instanceof ReflectionMethod) {
                                continue;
                            }
                            $methodName = $refMethod->getName();

                            $methods = [
                                $qcn . '->' . $methodName . '()' => [$refMethod, new \ReflectionMethod($qcn, $methodName)],
                            ];
                            foreach ($methods as $caseName => list($parsedClass, $originalClass)) {
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
