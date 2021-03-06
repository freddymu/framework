<?php
declare(strict_types=1);

/**
 * PHP Deal framework
 *
 * @copyright Copyright 2019, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpDeal\Aspect;

use Doctrine\Common\Annotations\Reader;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Aop\Support\AnnotatedReflectionMethod;
use PhpDeal\Annotation\Ensure;
use PhpDeal\Contract\Fetcher\Parent\MethodConditionFetcher;
use PhpDeal\Exception\ContractViolation;
use Go\Lang\Annotation\Around;
use ReflectionMethod;

class PostconditionCheckerAspect extends AbstractContractAspect implements Aspect
{
    /**
     * @var MethodConditionFetcher
     */
    private $methodConditionFetcher;

    public function __construct(Reader $reader)
    {
        parent::__construct($reader);
        $this->methodConditionFetcher = new MethodConditionFetcher([Ensure::class], $reader);
    }

    /**
     * Verifies post-condition contract for the method
     *
     * @Around("@execution(PhpDeal\Annotation\Ensure)")
     * @param MethodInvocation $invocation
     *
     * @throws ContractViolation
     * @throws \ReflectionException
     * @return mixed
     */
    public function postConditionContract(MethodInvocation $invocation)
    {
        $object = $invocation->getThis();
        $args   = $this->fetchMethodArguments($invocation);
        $class  = $invocation->getMethod()->getDeclaringClass();
        if (\is_object($object) && $class->isCloneable()) {
            $args['__old'] = clone $object;
        }

        $result = $invocation->proceed();
        $args['__result'] = $result;
        $allContracts = $this->fetchAllContracts($invocation);

        $this->ensureContracts($invocation, $allContracts, $object, $class->name, $args);

        return $result;
    }

    /**
     * @param MethodInvocation $invocation
     * @return array
     * @throws \ReflectionException
     */
    private function fetchAllContracts(MethodInvocation $invocation): array
    {
        $allContracts = $this->fetchParentsContracts($invocation);
        /** @var ReflectionMethod&AnnotatedReflectionMethod $reflectionMethod */
        $reflectionMethod = $invocation->getMethod();

        foreach ($reflectionMethod->getAnnotations() as $annotation) {
            if ($annotation instanceof Ensure) {
                $allContracts[] = $annotation;
            }
        }

        return \array_unique($allContracts);
    }

    /**
     * @param MethodInvocation $invocation
     * @return array
     * @throws \ReflectionException
     */
    private function fetchParentsContracts(MethodInvocation $invocation): array
    {
        return $this->methodConditionFetcher->getConditions(
            $invocation->getMethod()->getDeclaringClass(),
            $invocation->getMethod()->name
        );
    }
}
