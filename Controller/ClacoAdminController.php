<?php

namespace CPASimUSante\ClacoAdminBundle\Controller;

use JMS\DiExtraBundle\Annotation as DI;
use JMS\SecurityExtraBundle\Annotation as SEC;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

use CPASimUSante\ClacoAdminBundle\Manager\ClacoAdminManager;

/**
 * @DI\Tag("security.secure_service")
 * @SEC\PreAuthorize("canOpenAdminTool('cruncher')")
 * @EXT\Route(options={"expose"=true})
 * @EXT\Method("GET")
 */
class ClacoAdminController
{
    private $em;
    private $request;
    private $tokenStorage;
    private $container;
    private $clacoAdminManager;

    /**
     * @DI\InjectParams({
     *      "em"                = @DI\Inject("claroline.persistence.object_manager"),
     *      "request"           = @DI\Inject("request"),
     *      "tokenStorage"      = @DI\Inject("security.token_storage"),
     *      "container"         = @DI\Inject("service_container"),
     *      "clacoAdminManager" = @DI\Inject("cpasimusante.clacoadmin.clacoadmin_manager")
     * })
     */
    public function __construct(
        ObjectManager $em,
        Request $request,
        TokenStorageInterface $tokenStorage,
        ContainerInterface $container,
        ClacoAdminManager $clacoAdminManager
    )
    {
        $this->em = $em;
        $this->request = $request;
        $this->tokenStorage = $tokenStorage;
        $this->container = $container;
        $this->clacoAdminManager = $clacoAdminManager;
    }

    /**
     * Displays the index of the cruncher tool
     *
     * @EXT\Route("/cruncher", name="cpasimusante_cruncher")
     * @EXT\Template()
     *
     * @return array
     */
    public function cruncherAction()
    {
        return [
        ];
    }

    /**
     * Displays the index of the cruncher tool
     *
     * @EXT\Route("/import", name="cpasimusante_cruncher_import", options={"expose"=true})
     * @EXT\Method({"GET", "POST"})
     * @EXT\Template("CPASimUSanteClacoAdminBundle:ClacoAdmin:import.html.twig")
     * @return array
     */
    public function importAction()
    {
        $importexofull = $this->request->files->get('importexofull');
        $importexofile = $this->request->files->get('importexofile');
        $importexoquestion = $this->request->files->get('importexoquestion');
        $importexochoice = $this->request->files->get('importexochoice');
        $workspace = $this->request->get('workspace_to_import_to');

        $wslist = $this->clacoAdminManager->getWsList();

        if (isset($importexofull)) {
            $this->clacoAdminManager->importFile($importexofull, 'all', (int)$workspace);
        }
        if (isset($importexofile)) {
            //if ($questionfile->getMimeType() != 'text/csv')
            $this->clacoAdminManager->importFile($importexofile, 'exercise', (int)$workspace);
        }
        if (isset($importexoquestion)) {
            $this->clacoAdminManager->importFile($importexoquestion, 'question', '');
        }

        return array(
            'wslist' => $wslist,
        );
    }

}
