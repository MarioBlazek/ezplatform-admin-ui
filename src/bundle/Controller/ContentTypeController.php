<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace EzSystems\EzPlatformAdminUiBundle\Controller;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\API\Repository\Values\ContentType\ContentTypeDraft;
use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;
use EzSystems\EzPlatformAdminUi\Form\Data\ContentType\ContentTypesDeleteData;
use EzSystems\EzPlatformAdminUi\Form\Factory\FormFactory;
use EzSystems\EzPlatformAdminUi\Form\SubmitHandler;
use EzSystems\EzPlatformAdminUi\Notification\NotificationHandlerInterface;
use EzSystems\RepositoryForms\Data\Mapper\ContentTypeDraftMapper;
use EzSystems\RepositoryForms\Form\ActionDispatcher\ActionDispatcherInterface;
use EzSystems\RepositoryForms\Form\Type\ContentType\ContentTypeUpdateType;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use eZ\Publish\API\Repository\Exceptions\BadStateException;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Translation\Exception\InvalidArgumentException as TranslationInvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatorInterface;
use eZ\Publish\API\Repository\UserService;
use eZ\Publish\Core\MVC\Symfony\Security\Authorization\Attribute;

class ContentTypeController extends Controller
{
    /** @var \EzSystems\EzPlatformAdminUi\Notification\NotificationHandlerInterface */
    private $notificationHandler;

    /** @var \Symfony\Component\Translation\TranslatorInterface */
    private $translator;

    /** @var \eZ\Publish\API\Repository\ContentTypeService */
    private $contentTypeService;

    /** @var \EzSystems\RepositoryForms\Form\ActionDispatcher\ActionDispatcherInterface */
    private $contentTypeActionDispatcher;

    /** @var array */
    private $languages;

    /** @var \EzSystems\EzPlatformAdminUi\Form\Factory\FormFactory */
    private $formFactory;

    /** @var \EzSystems\EzPlatformAdminUi\Form\SubmitHandler */
    private $submitHandler;

    /** @var int */
    private $defaultPaginationLimit;

    /** @var \eZ\Publish\API\Repository\UserService */
    private $userService;

    /**
     * @param \EzSystems\EzPlatformAdminUi\Notification\NotificationHandlerInterface $notificationHandler
     * @param \Symfony\Component\Translation\TranslatorInterface $translator
     * @param \eZ\Publish\API\Repository\ContentTypeService $contentTypeService
     * @param \EzSystems\RepositoryForms\Form\ActionDispatcher\ActionDispatcherInterface $contentTypeActionDispatcher
     * @param \EzSystems\EzPlatformAdminUi\Form\Factory\FormFactory $formFactory
     * @param \EzSystems\EzPlatformAdminUi\Form\SubmitHandler $submitHandler
     * @param array $languages
     * @param int $defaultPaginationLimit
     * @param \eZ\Publish\API\Repository\UserService $userService
     */
    public function __construct(
        NotificationHandlerInterface $notificationHandler,
        TranslatorInterface $translator,
        ContentTypeService $contentTypeService,
        ActionDispatcherInterface $contentTypeActionDispatcher,
        FormFactory $formFactory,
        SubmitHandler $submitHandler,
        array $languages,
        int $defaultPaginationLimit,
        UserService $userService
    ) {
        $this->notificationHandler = $notificationHandler;
        $this->translator = $translator;
        $this->contentTypeService = $contentTypeService;
        $this->contentTypeActionDispatcher = $contentTypeActionDispatcher;
        $this->formFactory = $formFactory;
        $this->submitHandler = $submitHandler;
        $this->languages = $languages;
        $this->defaultPaginationLimit = $defaultPaginationLimit;
        $this->userService = $userService;
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $group
     * @param string $routeName
     * @param int $page
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @throws \Pagerfanta\Exception\OutOfRangeCurrentPageException
     * @throws \Pagerfanta\Exception\NotIntegerCurrentPageException
     * @throws \Pagerfanta\Exception\NotIntegerMaxPerPageException
     * @throws \Pagerfanta\Exception\LessThan1CurrentPageException
     * @throws \Pagerfanta\Exception\LessThan1MaxPerPageException
     */
    public function listAction(ContentTypeGroup $group, string $routeName, int $page): Response
    {
        $deletableTypes = [];

        $pagerfanta = new Pagerfanta(
            new ArrayAdapter($this->contentTypeService->loadContentTypes($group, $this->languages))
        );

        $pagerfanta->setMaxPerPage($this->defaultPaginationLimit);
        $pagerfanta->setCurrentPage(min($page, $pagerfanta->getNbPages()));

        /** @var \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup[] $contentTypeGroupList */
        $types = $pagerfanta->getCurrentPageResults();

        $deleteContentTypesForm = $this->formFactory->deleteContentTypes(
            new ContentTypesDeleteData($this->getContentTypesNumbers($types))
        );

        foreach ($types as $type) {
            $deletableTypes[$type->id] = !$this->contentTypeService->isContentTypeUsed($type);
        }

        return $this->render('@ezdesign/admin/content_type/list.html.twig', [
            'content_type_group' => $group,
            'pager' => $pagerfanta,
            'deletable' => $deletableTypes,
            'form_content_types_delete' => $deleteContentTypesForm->createView(),
            'group' => $group,
            'route_name' => $routeName,
            'can_create' => $this->isGranted(new Attribute('class', 'create')),
            'can_update' => $this->isGranted(new Attribute('class', 'update')),
            'can_delete' => $this->isGranted(new Attribute('class', 'delete')),
        ]);
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $group
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentTypeFieldDefinitionValidationException
     */
    public function addAction(ContentTypeGroup $group): Response
    {
        random nonsense;

        $this->denyAccessUnlessGranted(new Attribute('class', 'create'));
        $mainLanguageCode = reset($this->languages);

        $createStruct = $this->contentTypeService->newContentTypeCreateStruct('__new__' . md5((string)microtime(true)));
        $createStruct->mainLanguageCode = $mainLanguageCode;
        $createStruct->names = [$mainLanguageCode => 'New Content Type'];

        try {
            $contentTypeDraft = $this->contentTypeService->createContentType($createStruct, [$group]);
        } catch (NotFoundException $e) {
            $this->notificationHandler->error(
                $this->translator->trans(
                    /** @Desc("Cannot create Content Type. Could not find 'Language' with identifier '%languageCode%'") */
                    'content_type.add.missing_language',
                    ['%languageCode%' => $mainLanguageCode],
                    'content_type'
                )
            );

            return $this->redirectToRoute('ezplatform.content_type_group.view', [
                'contentTypeGroupId' => $group->id,
            ]);
        }

        $form = $this->createUpdateForm($group, $contentTypeDraft);

        return $this->render('@ezdesign/admin/content_type/create.html.twig', [
            'content_type_group' => $group,
            'content_type' => $contentTypeDraft,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $group
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentType $contentType
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     */
    public function editAction(ContentTypeGroup $group, ContentType $contentType): Response
    {
        $this->denyAccessUnlessGranted(new Attribute('class', 'update'));
        // Kernel does not allow editing the same Content Type simultaneously by more than one user.
        // So we need to catch 'BadStateException' and inform user about another user editing the Content Type
        try {
            $contentTypeDraft = $this->contentTypeService->loadContentTypeDraft($contentType->id);
            $this->contentTypeService->deleteContentType($contentTypeDraft);
            $contentTypeDraft = $this->contentTypeService->createContentTypeDraft($contentType);
        } catch (NotFoundException $e) {
            try {
                $contentTypeDraft = $this->contentTypeService->createContentTypeDraft($contentType);
            } catch (BadStateException $e) {
                $userId = $contentType->modifierId;
                $this->notificationHandler->error(
                    $this->translator->trans(
                        /** @Desc("Draft of the Content Type '%name%' already exists and is locked by '%userContentName%'") */
                        'content_type.edit.error.already_exists',
                        ['%name%' => $contentType->getName(), '%userContentName%' => $this->getUserNameById($userId)],
                        'content_type'
                    )
                );

                return $this->redirectToRoute('ezplatform.content_type.view', [
                    'contentTypeGroupId' => $group->id,
                    'contentTypeId' => $contentType->id,
                ]);
            }
        }

        return $this->redirectToRoute('ezplatform.content_type.update', [
            'contentTypeId' => $contentTypeDraft->id,
            'contentTypeGroupId' => $group->id,
        ]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $group
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeDraft $contentTypeDraft
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Symfony\Component\Translation\Exception\InvalidArgumentException
     */
    public function updateAction(Request $request, ContentTypeGroup $group, ContentTypeDraft $contentTypeDraft): Response
    {
        $form = $this->createUpdateForm($group, $contentTypeDraft);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $result = $this->submitHandler->handle($form, function () use ($form, $group, $contentTypeDraft) {
                $languageCode = reset($this->languages);

                foreach ($this->languages as $prioritizedLanguage) {
                    if (isset($contentTypeDraft->names[$prioritizedLanguage])) {
                        $languageCode = $prioritizedLanguage;
                        break;
                    }
                }

                $this->contentTypeActionDispatcher->dispatchFormAction(
                    $form,
                    $form->getData(),
                    $form->getClickedButton() ? $form->getClickedButton()->getName() : null,
                    ['languageCode' => $languageCode]
                );

                if ($response = $this->contentTypeActionDispatcher->getResponse()) {
                    return $response;
                }

                $this->notificationHandler->success(
                    $this->translator->trans(
                        /** @Desc("Content Type '%name%' updated.") */
                        'content_type.update.success',
                        ['%name%' => $contentTypeDraft->getName()],
                        'content_type'
                    )
                );

                $routeName = 'publishContentType' === $form->getClickedButton()->getName()
                    ? 'ezplatform.content_type.view'
                    : 'ezplatform.content_type.update';

                return $this->redirectToRoute($routeName, [
                    'contentTypeGroupId' => $group->id,
                    'contentTypeId' => $contentTypeDraft->id,
                ]);
            });

            if ($result instanceof Response) {
                return $result;
            }
        }

        return $this->render('@ezdesign/admin/content_type/edit.html.twig', [
            'content_type_group' => $group,
            'content_type' => $contentTypeDraft,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $group
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentType $contentType
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Symfony\Component\Translation\Exception\InvalidArgumentException
     */
    public function deleteAction(Request $request, ContentTypeGroup $group, ContentType $contentType): Response
    {
        $this->denyAccessUnlessGranted(new Attribute('class', 'delete'));
        $form = $this->createDeleteForm($group, $contentType);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $result = $this->submitHandler->handle($form, function () use ($contentType) {
                $this->contentTypeService->deleteContentType($contentType);

                $this->notificationHandler->success(
                    $this->translator->trans(
                        /** @Desc("Content Type '%name%' deleted.") */
                        'content_type.delete.success',
                        ['%name%' => $contentType->getName()],
                        'content_type'
                    )
                );
            });

            if ($result instanceof Response) {
                return $result;
            }
        }

        return $this->redirectToRoute('ezplatform.content_type_group.view', [
            'contentTypeGroupId' => $group->id,
        ]);
    }

    /**
     * Handles removing content types based on submitted form.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $group
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws TranslationInvalidArgumentException
     * @throws InvalidOptionsException
     * @throws \InvalidArgumentException
     */
    public function bulkDeleteAction(Request $request, ContentTypeGroup $group): Response
    {
        $this->denyAccessUnlessGranted(new Attribute('class', 'delete'));
        $form = $this->formFactory->deleteContentTypes(
            new ContentTypesDeleteData()
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->submitHandler->handle($form, function (ContentTypesDeleteData $data) {
                foreach ($data->getContentTypes() as $contentTypeId => $selected) {
                    $contentType = $this->contentTypeService->loadContentType($contentTypeId);

                    $this->contentTypeService->deleteContentType($contentType);

                    $this->notificationHandler->success(
                        $this->translator->trans(
                            /** @Desc("Content Type '%name%' deleted.") */
                            'content_type.delete.success',
                            ['%name%' => $contentType->getName()],
                            'content_type'
                        )
                    );
                }
            });

            if ($result instanceof Response) {
                return $result;
            }
        }

        return $this->redirect($this->generateUrl('ezplatform.content_type_group.view', ['contentTypeGroupId' => $group->id]));
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $group
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentType $contentType
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewAction(ContentTypeGroup $group, ContentType $contentType): Response
    {
        $fieldDefinitionsByGroup = [];
        foreach ($contentType->fieldDefinitions as $fieldDefinition) {
            $fieldDefinitionsByGroup[$fieldDefinition->fieldGroup ?: 'content'][] = $fieldDefinition;
        }

        return $this->render('@ezdesign/admin/content_type/view.html.twig', [
            'content_type_group' => $group,
            'content_type' => $contentType,
            'field_definitions_by_group' => $fieldDefinitionsByGroup,
            'can_update' => $this->isGranted(new Attribute('class', 'update')),
        ]);
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $group
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeDraft $contentTypeDraft
     *
     * @return \Symfony\Component\Form\FormInterface
     */
    public function createUpdateForm(ContentTypeGroup $group, ContentTypeDraft $contentTypeDraft): FormInterface
    {
        $contentTypeData = (new ContentTypeDraftMapper())->mapToFormData(
            $contentTypeDraft
        );

        $languageCode = reset($this->languages);

        foreach ($this->languages as $prioritizedLanguage) {
            if (isset($contentTypeDraft->names[$prioritizedLanguage])) {
                $languageCode = $prioritizedLanguage;
                break;
            }
        }

        return $this->createForm(ContentTypeUpdateType::class, $contentTypeData, [
            'method' => Request::METHOD_POST,
            'action' => $this->generateUrl('ezplatform.content_type.update', [
                'contentTypeGroupId' => $group->id,
                'contentTypeId' => $contentTypeDraft->id,
            ]),
            'languageCode' => $languageCode,
        ]);
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $group
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentType $contentType
     *
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function createDeleteForm(ContentTypeGroup $group, ContentType $contentType): FormInterface
    {
        $formBuilder = $this->createFormBuilder(null, [
            'method' => Request::METHOD_DELETE,
            'action' => $this->generateUrl('ezplatform.content_type.delete', [
                'contentTypeGroupId' => $group->id,
                'contentTypeId' => $contentType->id,
            ]),
        ]);

        return $formBuilder->getForm();
    }

    /**
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentType[] $contentTypes
     *
     * @return array
     */
    private function getContentTypesNumbers(array $contentTypes): array
    {
        $contentTypesNumbers = array_column($contentTypes, 'id');

        return array_combine($contentTypesNumbers, array_fill_keys($contentTypesNumbers, false));
    }

    /**
     * @param int $userId
     *
     * @return string|null
     *
     * @throws \Symfony\Component\Translation\Exception\InvalidArgumentException
     */
    private function getUserNameById(int $userId): ?string
    {
        try {
            $user = $this->userService->loadUser($userId);

            return $user->getName();
        } catch (\Exception $e) {
            return $this->translator->trans(
                /** @Desc("another user") */
                'content_type.user_name.can_not_be_fetched',
                [],
                'content_type'
            );
        }
    }
}
