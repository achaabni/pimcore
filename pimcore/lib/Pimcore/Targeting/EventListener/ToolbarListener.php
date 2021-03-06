<?php

declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Targeting\EventListener;

use Pimcore\Bundle\CoreBundle\EventListener\Traits\PimcoreContextAwareTrait;
use Pimcore\Http\Request\Resolver\DocumentResolver;
use Pimcore\Http\Request\Resolver\PimcoreContextResolver;
use Pimcore\Http\Response\CodeInjector;
use Pimcore\Model\Document;
use Pimcore\Targeting\Debug\TargetingDataCollector;
use Pimcore\Targeting\Model\VisitorInfo;
use Pimcore\Targeting\VisitorInfoStorageInterface;
use Pimcore\Tool\Authentication;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Templating\EngineInterface;

class ToolbarListener implements EventSubscriberInterface
{
    use PimcoreContextAwareTrait;

    /**
     * @var VisitorInfoStorageInterface
     */
    private $visitorInfoStorage;

    /**
     * @var DocumentResolver
     */
    private $documentResolver;

    /**
     * @var TargetingDataCollector
     */
    private $targetingDataCollector;

    /**
     * @var EngineInterface
     */
    private $templatingEngine;

    /**
     * @var CodeInjector
     */
    private $codeInjector;

    public function __construct(
        VisitorInfoStorageInterface $visitorInfoStorage,
        DocumentResolver $documentResolver,
        TargetingDataCollector $targetingDataCollector,
        EngineInterface $templatingEngine,
        CodeInjector $codeInjector
    ) {
        $this->visitorInfoStorage     = $visitorInfoStorage;
        $this->documentResolver       = $documentResolver;
        $this->targetingDataCollector = $targetingDataCollector;
        $this->templatingEngine       = $templatingEngine;
        $this->codeInjector           = $codeInjector;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -127],
        ];
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->matchesPimcoreContext($request, PimcoreContextResolver::CONTEXT_DEFAULT)) {
            return;
        }

        // only inject toolbar for logged in admin users
        $adminUser = Authentication::authenticateSession($request);
        if (!$adminUser) {
            return;
        }

        // only inject toolbar if there's a visitor info
        if (!$this->visitorInfoStorage->hasVisitorInfo()) {
            return;
        }

        $cookieValue = (bool)$request->cookies->get('pimcore_targeting_debug', false);
        if (!$cookieValue) {
            return;
        }

        $document    = $this->documentResolver->getDocument($request);
        $visitorInfo = $this->visitorInfoStorage->getVisitorInfo();
        $data        = $this->collectTemplateData($visitorInfo, $document);

        $this->injectToolbar(
            $event->getResponse(),
            $data
        );
    }

    private function collectTemplateData(VisitorInfo $visitorInfo, Document $document = null)
    {
        $token = substr(hash('sha256', uniqid((string)mt_rand(), true)), 0, 6);

        $tdc = $this->targetingDataCollector;

        $data = [
            'token'                => $token,
            'visitorInfo'          => $tdc->collectVisitorInfo($visitorInfo),
            'targetGroups'         => $tdc->collectTargetGroups($visitorInfo),
            'rules'                => $tdc->collectMatchedRules($visitorInfo),
            'documentTargetGroup'  => $tdc->collectDocumentTargetGroup($document),
            'documentTargetGroups' => $tdc->collectDocumentTargetGroupMapping(),
        ];

        return $data;
    }

    private function injectToolbar(Response $response, array $data)
    {
        $code = $this->templatingEngine->render('@PimcoreCore/Targeting/toolbar/toolbar.html.twig', $data);

        $this->codeInjector->inject(
            $response,
            $code,
            CodeInjector::SELECTOR_BODY,
            CodeInjector::POSITION_END
        );
    }
}
