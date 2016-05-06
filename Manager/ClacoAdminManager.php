<?php

namespace CPASimUSante\ClacoAdminBundle\Manager;

use JMS\DiExtraBundle\Annotation as DI;
use Doctrine\ORM\EntityManager;

use Claroline\CoreBundle\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Translation\TranslatorInterface;

use UJM\ExoBundle\Form\ExerciseType;
use UJM\ExoBundle\Form\ExerciseHandler;
use UJM\ExoBundle\Entity\Exercise;
use UJM\ExoBundle\Entity\ExerciseQuestion;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Resource\ResourceType;
use UJM\ExoBundle\Entity\Question;
use UJM\ExoBundle\Entity\InteractionQCM;
use UJM\ExoBundle\Entity\Category;
use UJM\ExoBundle\Entity\Choice;

/**
 * @DI\Service("cpasimusante.clacoadmin.clacoadmin_manager")
 */
class ClacoAdminManager
{
    private $om;
    private $container;
    private $tokenStorage;
    private $session;
    private $translator;

    /**
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "container"      = @DI\Inject("service_container"),
     *     "tokenStorage"   = @DI\Inject("security.token_storage"),
     *     "session"        = @DI\Inject("session"),
     *     "translator"     = @DI\Inject("translator")
     * })
     *
     * @param ObjectManager $om
     */
    public function __construct(
        ObjectManager $om,
        ContainerInterface $container,
        TokenStorageInterface $tokenStorage,
        SessionInterface $session,
        TranslatorInterface $translator
    )
    {
        $this->om           = $om;
        $this->container    = $container;
        $this->tokenStorage = $tokenStorage;
        $this->session      = $session;
        $this->translator   = $translator;
    }

    public function importFile($file, $datatype)
    {
        $sessionFlashBag = $this->session->getFlashBag();
        $datalines = array();

        $data = file_get_contents($file);
        $data = $this->container->get('claroline.utilities.misc')
            ->formatCsvOutput($data);
        $lines = str_getcsv($data, PHP_EOL);
        foreach ($lines as $line) {
            //data separated with ;
            $datalines[] = str_getcsv($line, ';');
        }

        if ($datalines != array()) {
            $createddata = array();

            if ($datatype == 'all') {
                $createddata = $this->importFullExercise($datalines, $createddata);
            } elseif ($datatype == 'exercise') {
                $createddata = $this->importExercise($datalines, $createddata);
            } elseif ($datatype == 'question') {
                $createddata = $this->importQuestion($datalines, $createddata);
            }

            if (isset($createddata['ok'])) {
                foreach ($createddata['ok'] as $created) {
                    $msg =  '<' . $created . '> ';
                    $msg .= $this->translator->trans(
                    'has_been_created',
                    array(),
                    'platform'
                    );
                    $sessionFlashBag->add('success', $msg);
                }
            }
        }
    }

    public function importFullExercise(array $exercises, $returnValues=array())
    {
        $user = $this->tokenStorage->getToken()->getUser();
        //get icon
        $exoIcon = $this->om
            ->getRepository('ClarolineCoreBundle:Resource\ResourceIcon')
            ->findOneBy(array('isShortcut'=>false, 'mimeType'=> 'custom/ujm_exercise'));

        //get type for resourcenode
        $exoType = $this->om
            ->getRepository('ClarolineCoreBundle:Resource\ResourceType')
            ->findOneByName('ujm_exercise');

        //define category if necessary
        $category = $this->om->getRepository('UJMExoBundle:Category')
        ->findOneBy(array('value' => 'import', 'user'=>$user));//default category
        if ($category == null) {
            $category = new Category();
            $category->setValue('import');
            $category->setUser($user);
            $this->om->persist($category);
        }

        $this->om->startFlushSuite();

        $tmpexercise = null;
        $tmpquestion = null;
        $tmpchoice = null;

        for ($inc=0;$inc<count($exercises);$inc++) {
            if (isset($exercises[$inc]) && $exercises[$inc][0] == '-EXERCISE-') {
                $inc++;
                $exercise = $exercises[$inc];

                $node = new ResourceNode();
                $node->setName($exercise[0]);
                $node->setMimeType('custom/ujm_exercise');
                $node->setResourceType($exoType);
    //TODO : choose WS
                $node->setWorkspace($user->getPersonalWorkspace());
    //TODO : choose user
                $node->setCreator($user);
    //TODO : set correct GUID
                $node->setGuid(time());
                $node->setClass('UJM\ExoBundle\Entity\Exercise');
                $node->setIcon($exoIcon);
                $node->setIndex(1);
                $this->om->persist($node);

                $newExercise = new Exercise();
                $newExercise->setTitle($exercise[0]);
                if (isset($exercise[1])) {
                    $newExercise->setDescription($exercise[1]);
                }
                $newExercise->setResourceNode($node);
                $this->om->persist($newExercise);

                $tmpexercise = $newExercise;
            }

            if (isset($exercises[$inc]) && $exercises[$inc][0] == '-QUESTION-') {
                $inc++;
                $question = $exercises[$inc];
                //Create question
                $newQuestion = new Question();
                $newQuestion->setTitle($question[0]);
                $newQuestion->setDateCreate(new \DateTime());
                $newQuestion->setType($question[2]);
                $newQuestion->setInvite($question[1]);
                $newQuestion->setCategory($category);
                $newQuestion->setUser($user);
                $this->om->persist($newQuestion);

                //create ujm_exercise_question
                $newExoQ = new ExerciseQuestion($newExercise, $newQuestion);
                $newExoQ->setOrdre(0);
                $this->om->persist($newExoQ);

                //create ujm_interaction_qcm
                $typeqcm = $this->om->getRepository('UJMExoBundle:TypeQCM')
                    ->findOneBy(array('code' => $question[3])); //2 = QCU
                $newInteraction = new InteractionQCM();
                $newInteraction->setTypeQCM($typeqcm);
                $right = isset($question[3]) ? $question[4] : 0;
                $false = isset($question[4]) ? $question[5] : 0;
                $newInteraction->setScoreRightResponse($right);
                $newInteraction->setScoreFalseResponse($false);
                $newInteraction->setQuestion($newQuestion);
                $this->om->persist($newInteraction);

                $tmpquestion = $newQuestion;
            }

            if (isset($exercises[$inc]) && $exercises[$inc][0] == '-CHOICE-') {
                $inc++;
                $choice = $exercises[$inc];
                $newChoice = new Choice();
                $newChoice->setInteractionQCM($newInteraction);
                $newChoice->setLabel($choice[0]);
                $newChoice->setRightResponse($choice[1]);
                $newChoice->setOrdre($choice[2]);
                $newChoice->setWeight($choice[3]);
                $newChoice->setPositionForce($choice[4]);
                $this->om->persist($newChoice);
            }
        }
        $this->om->endFlushSuite();
    }

    public function importExercise(array $exercises, $returnValues=array())
    {
        $user = $this->tokenStorage->getToken()->getUser();
        //get icon
        $exoIcon = $this->om->getRepository('ClarolineCoreBundle:Resource\ResourceIcon')
        ->findOneBy(array('isShortcut'=>false, 'mimeType'=> 'custom/ujm_exercise'));

        //get type for resourcenode
        $exoType = $this->om->getRepository('ClarolineCoreBundle:Resource\ResourceType')
        ->findOneByName('ujm_exercise');

        $this->om->startFlushSuite();
        foreach ($exercises as $exercise) {
            //Create resourcenode
            $node = new ResourceNode();
            $node->setName($exercise[0]);
            $node->setMimeType('custom/ujm_exercise');
            $node->setResourceType($exoType);
//TODO : choose WS
            $node->setWorkspace($user->getPersonalWorkspace());
//TODO : choose user
            $node->setCreator($user);
//TODO : set correct GUID
            $node->setGuid(time());
            $node->setClass('UJM\ExoBundle\Entity\Exercise');
            $node->setIcon($exoIcon);
            $node->setIndex(1);
            $this->om->persist($node);

            $newExercise = new Exercise();
            $newExercise->setTitle($exercise[0]);
            if (isset($exercise[1])) {
                $newExercise->setDescription($exercise[1]);
            }
            $newExercise->setResourceNode($node);
            $this->om->persist($newExercise);

            $returnValues['ok'][] = $exercise[0];
        }
        $this->om->endFlushSuite();

        return $returnValues;
    }

    public function importQuestion(array $questions, $returnValues=array())
    {
        $user = $this->tokenStorage->getToken()->getUser();

        $this->om->startFlushSuite();

        //define category if necessary
        $category = $this->om->getRepository('UJMExoBundle:Category')
        ->findOneBy(array('value' => 'import', 'user'=>$user));//default category
        if ($category == null) {
            $category = new Category();
            $category->setValue('import');
            $category->setUser($user);
            $this->om->persist($category);
        }

        foreach ($questions as $question) {
            //Create question
            $newQuestion = new Question();
            $newQuestion->setTitle($question['question']['title']);
            $newQuestion->setDateCreate(new \DateTime());
            $newQuestion->setType($question['question']['type']);
            $newQuestion->setInvite($question['question']['invite']);
            $newQuestion->setCategory($category);
            $newQuestion->setUser($user);
            $this->om->persist($newQuestion);

            //create ujm_exercise_question
            $newExoQ = new ExerciseQuestion($newExercise, $newQuestion);
            $newExoQ->setOrdre(0);
            $this->om->persist($newExoQ);

            //create ujm_interaction_qcm
            $typeqcm = $this->om->getRepository('UJMExoBundle:TypeQCM')
            ->findOneBy(array('code' => $question['interaction']['type'])); //2 = QCU
            $newInteraction = new InteractionQCM();
            $newInteraction->setTypeQCM($typeqcm);
            $newInteraction->setScoreRightResponse($question['interaction']['right']);
            $newInteraction->setScoreFalseResponse($question['interaction']['false']);
            $newInteraction->setQuestion($newQuestion);
            $this->om->persist($newInteraction);

            $returnValues['ok'][] = $exercise[0];
        }
        $this->om->endFlushSuite();

        return $returnValues;
    }

    public function dummy()
    {
        //echo '<pre>$sim->getResourceNode()->getWorkspace()->getId';var_dump($sim->getResourceNode()->getWorkspace()->getId());echo '</pre>';
        //        if ($request->isMethod('POST')) {
        //            $exerfile = $request->files->get('exerfile');

        //            if (isset($exerfile)) {
        //if ($questionfile->getMimeType() != 'text/csv')

        /*
        $this->em->startFlushSuite();

                foreach ($questions as $question) {
                //create ujm_question
                $newQuestion = new Question();
                $newQuestion->setTitle($question['question']['title']);
                $newQuestion->setDateCreate(new \DateTime());
                $newQuestion->setType($question['question']['type']);
                $newQuestion->setInvite($question['question']['invite']);
                $newQuestion->setCategory($category);
                $newQuestion->setUser($user);
                $this->em->persist($newQuestion);

                //create ujm_exercise_question
                $newExoQ = new ExerciseQuestion($newExercise, $newQuestion);
                $newExoQ->setOrdre(0);
                $this->em->persist($newExoQ);

                //create ujm_interaction_qcm
                $typeqcm = $this->em->getRepository('UJMExoBundle:TypeQCM')
                ->findOneBy(array('code' => $question['interaction']['type'])); //2 = QCU
                $newInteraction = new InteractionQCM();
                $newInteraction->setTypeQCM($typeqcm);
                $newInteraction->setScoreRightResponse($question['interaction']['right']);
                $newInteraction->setScoreFalseResponse($question['interaction']['false']);
                $newInteraction->setQuestion($newQuestion);
                $this->em->persist($newInteraction);

                //create ujm_choice
                foreach ($question['interaction']['choices'] as $choice) {
                $newChoice = new Choice();
                $newChoice->setInteractionQCM($newInteraction);
                $newChoice->setLabel($choice['label']);
                $newChoice->setOrdre($choice['order']);
                $newChoice->setWeight($choice['weight']);
                $newChoice->setPositionForce($choice['force']);
                $newChoice->setRightResponse($choice['right']);
                $this->em->persist($newChoice);
            }
        }

        $this->em->endFlushSuite();
        */
    }
}
