<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-api-problem for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-api-problem/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-api-problem/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\ApiTools\ApiProblem\Listener;

use Laminas\ApiTools\ApiProblem\Exception\DomainException;
use Laminas\ApiTools\ApiProblem\Listener\ApiProblemListener;
use Laminas\Http\Request;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\Stdlib\RequestInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class ApiProblemListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var MvcEvent
     */
    private $event;

    /**
     * @var ApiProblemListener
     */
    private $listener;

    protected function setUp(): void
    {
        $this->event = new MvcEvent();
        $this->event->setError('this is an error event');
        $this->listener = new ApiProblemListener();
    }

    public function testOnRenderReturnsEarlyWhenNonHttpRequestDetected(): void
    {
        $request = $this->prophesize(RequestInterface::class)->reveal();
        $this->event->setRequest($request);

        self::assertNull($this->listener->onRender($this->event));
    }

    public function testOnDispatchErrorReturnsAnApiProblemResponseBasedOnCurrentEventException(): void
    {
        $request = new Request();
        $request->getHeaders()->addHeaderLine('Accept', 'application/json');

        $event = new MvcEvent();
        $event->setError(Application::ERROR_EXCEPTION);
        $event->setParam('exception', new DomainException('triggering exception', 400));
        $event->setRequest($request);
        $return = $this->listener->onDispatchError($event);

        self::assertTrue($event->propagationIsStopped());
        self::assertInstanceOf('Laminas\ApiTools\ApiProblem\ApiProblemResponse', $return);
        $response = $event->getResponse();
        self::assertSame($return, $response);
        $problem = $response->getApiProblem();
        self::assertInstanceOf('Laminas\ApiTools\ApiProblem\ApiProblem', $problem);
        self::assertEquals(400, $problem->status);
        self::assertSame($event->getParam('exception'), $problem->detail);
    }

    /**
     * @requires PHP 7.0
     */
    public function testOnDispatchErrorReturnsAnApiProblemResponseBasedOnCurrentEventThrowable(): void
    {
        $request = new Request();
        $request->getHeaders()->addHeaderLine('Accept', 'application/json');

        $event = new MvcEvent();
        $event->setError(Application::ERROR_EXCEPTION);
        $event->setParam('exception', new \TypeError('triggering throwable', 400));
        $event->setRequest($request);
        $return = $this->listener->onDispatchError($event);

        self::assertTrue($event->propagationIsStopped());
        self::assertInstanceOf('Laminas\ApiTools\ApiProblem\ApiProblemResponse', $return);
        $response = $event->getResponse();
        self::assertSame($return, $response);
        $problem = $response->getApiProblem();
        self::assertInstanceOf('Laminas\ApiTools\ApiProblem\ApiProblem', $problem);
        self::assertEquals(400, $problem->status);
        self::assertSame($event->getParam('exception'), $problem->detail);
    }
}
