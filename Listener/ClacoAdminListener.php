<?php

namespace CPASimUSante\ClacoAdminBundle\Listener;

use Claroline\CoreBundle\Event\CustomActionResourceEvent;
use Claroline\CoreBundle\Event\DisplayToolEvent;
use Claroline\CoreBundle\Event\DisplayWidgetEvent;
use Claroline\CoreBundle\Event\OpenAdministrationToolEvent;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use CPASimUSante\SimupollBundle\Entity\Paper;

/**
 * Defines the listening methods for all the core extension
 * points used in this plugin (tools and widgets).
 *
 * @DI\Service("cpasimusante.clacoadmin.clacoadmin_listener")
 */
class ClacoAdminListener
{
    private $request;
    private $kernel;

    /**
     * @DI\InjectParams({
     *     "stack"  = @DI\Inject("request_stack"),
     *     "kernel" = @DI\Inject("http_kernel")
     * })
     *
     * @param RequestStack $stack
     */
    public function __construct(RequestStack $stack, HttpKernelInterface $kernel)
    {
        $this->request = $stack->getCurrentRequest();
        $this->kernel = $kernel;
    }

    /**
     * @DI\Observe("administration_tool_cruncher")
     *
     * @param OpenAdministrationToolEvent $event
     */
    public function onOpenClacoAdminTool(OpenAdministrationToolEvent $event)
    {
        $this->forward('CPASimUSanteClacoAdminBundle:ClacoAdmin:cruncher', $event);
    }

    private function forward($controller, Event $event)
    {
        $attributes = ['_controller' => $controller];

        if ($event instanceof CustomActionResourceEvent) {
            $attributes['id'] = $event->getResource()->getId();
        }

        $subRequest = $this->request->duplicate([], null, $attributes);
        $response = $this->kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);

        if ($event instanceof DisplayToolEvent || $event instanceof DisplayWidgetEvent) {
            $event->setContent($response->getContent());
        } else {
            $event->setResponse($response);
        }

        $event->stopPropagation();
    }
}
