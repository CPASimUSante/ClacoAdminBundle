<?php

namespace CPASimUSante\ClacoAdminBundle\Controller;

use JMS\DiExtraBundle\Annotation as DI;
use JMS\SecurityExtraBundle\Annotation as SEC;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * @DI\Tag("security.secure_service")
 * @SEC\PreAuthorize("canOpenAdminTool('cruncher')")
 * @EXT\Route(options={"expose"=true})
 * @EXT\Method("GET")
 */
class ClacoAdminController
{
    /**
     * Displays the index of the cruncher tool
     *
     * @EXT\Route("/cruncher", name="cpasimusante_cruncher")
     * @EXT\Template
     *
     * @return array
     */
    public function cruncherAction()
    {
        return [
        ];
    }
}
