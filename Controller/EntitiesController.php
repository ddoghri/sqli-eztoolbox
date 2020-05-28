<?php

namespace SQLI\EzToolboxBundle\Controller;

use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use ReflectionException;
use SQLI\EzToolboxBundle\Annotations\Annotation\Entity;
use SQLI\EzToolboxBundle\Classes\Filter;
use SQLI\EzToolboxBundle\Form\EntityManager\EditElementType;
use SQLI\EzToolboxBundle\Form\EntityManager\FilterType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntitiesController extends Controller
{
    /**
     * Display all entities annotated with SQLIAdmin\Entity
     *
     * @param $tabname
     * @return Response
     * @throws ReflectionException
     */
    public function listAllEntitiesAction( $tabname )
    {
        $this->denyAccessUnlessGranted( 'ez:sqli_admin:list_entities' );

        $tabs              = $this->get( 'sqli_admin_tab_entities' )->entitiesGroupedByTab();
        $params['tabname'] = $tabname;
        $params['classes'] = $tabs[$tabname];

        return $this->render( 'SQLIEzToolboxBundle:Entities:listAllEntities.html.twig', $params );
    }

    /**
     * Display an entity (lines in SQL table)
     *
     * @param string  $fqcn
     * @param string  $sort_column
     * @param string  $sort_order
     * @param Request $request
     * @return Response
     * @throws ReflectionException
     */
    public function showEntityAction( $fqcn, $sort_column, $sort_order, Request $request )
    {
        $this->denyAccessUnlessGranted( 'ez:sqli_admin:entity_show' );

        $classInformations = $this->get( 'sqli_admin_entities' )->getAnnotatedClass( $fqcn );

        $sort = [ 'column_name' => $sort_column, 'order' => $sort_order ];

        // FormType : class_informations
        $filter     = new Filter();
        $filterForm = $this->createForm( FilterType::class, $filter, [ 'class_informations' => $classInformations ] );

        $filterForm->handleRequest( $request );

        if( $filterForm->isSubmitted() && $filterForm->isValid() )
        {
            // Set filter in session, it will be retrieved in getEntity()
            $this->get( 'sqli_admin_filter_entity' )->setFilter( $fqcn, $filter );
            // Entity informations and all elements with sort (filter in session)
            $params = $this->get( 'sqli_admin_entities' )->getEntity( $fqcn, true, $sort );
        }
        else
        {
            // Entity informations and all elements without any filter
            $params = $this->get( 'sqli_admin_entities' )->getEntity( $fqcn, true, $sort );
        }

        // Generate filter form for the view
        $params['filter_form'] = $filterForm->createView();

        // Change current page on PagerFanta
        /** @var Entity $classAnnotation */
        $classAnnotation = $params['class']['annotation'];
        // Create a pager from array of elements
        $pager = new Pagerfanta( new ArrayAdapter( $params['elements'] ) );
        // Define max elements per page (can be defined in class' annotation)
        $pager->setMaxPerPage( $classAnnotation->getMaxPerPage() );
        // Define current page
        $pager->setCurrentPage( $request->get( 'page', 1 ) );
        // Set pager for template
        $params['pager'] = $pager;

        return $this->render( 'SQLIEzToolboxBundle:Entities:showEntity.html.twig', $params );
    }

    /**
     * Remove an element
     *
     * @param $fqcn
     * @param $compound_id string Compound primary key in JSON string
     * @return Response
     * @throws ReflectionException
     */
    public function removeElementAction( $fqcn, $compound_id )
    {
        $this->denyAccessUnlessGranted( 'ez:sqli_admin:entity_remove_element' );

        $removeSuccessfull = false;

        // Check if class annotation allow deletion
        $entity = $this->get( 'sqli_admin_entities' )->getEntity( $fqcn, false );

        if( array_key_exists( 'class', $entity ) && array_key_exists( 'annotation', $entity['class'] ) )
        {
            $entityAnnotation = $entity['class']['annotation'];
            // Check if annotation exists
            if( $entityAnnotation instanceof Entity )
            {
                // Check if deletion is allowed
                if( $entityAnnotation->isDelete() )
                {
                    // Try to decode compound Id
                    $compound_id = json_decode( $compound_id, true );

                    // If valid compound Id, remove element
                    if( !empty( $compound_id ) )
                    {
                        $this->get( 'sqli_admin_entities' )->remove( $fqcn, $compound_id );
                        $removeSuccessfull = true;
                    }
                }
            }
        }

        if( $removeSuccessfull )
        {
            // Display success notification
            $this
                ->get( 'EzSystems\EzPlatformAdminUi\Notification\FlashBagNotificationHandler' )
                ->success( $this
                               ->get( 'translator' )
                               ->trans( 'entity.element.deleted', [], 'sqli_admin' ) );
        }
        else
        {
            // Display error notification
            $this
                ->get( 'EzSystems\EzPlatformAdminUi\Notification\FlashBagNotificationHandler' )
                ->error( $this
                             ->get( 'translator' )
                             ->trans( 'entity.element.cannot_delete', [], 'sqli_admin' ) );
        }

        // Redirect to entity homepage (list of elements)
        return $this->redirectToRoute( 'sqli_eztoolbox_entitymanager_entity_homepage',
                                       [ 'fqcn' => $fqcn ] );
    }

    /**
     * Delete filter for specified FQCN and redirect to entity view
     *
     * @param $fqcn
     * @return RedirectResponse
     */
    public function resetFilterAction( $fqcn )
    {
        $this->get( 'sqli_admin_filter_entity' )->resetFilter( $fqcn );

        return $this->redirectToRoute( 'sqli_eztoolbox_entitymanager_entity_homepage',
                                       [ 'fqcn' => $fqcn ] );
    }

    /**
     * Show edit form and save modifications
     *
     * @param string  $fqcn FQCN
     * @param string  $compound_id Json format
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws ReflectionException
     */
    public function editElementAction( $fqcn, $compound_id, Request $request )
    {
        $this->denyAccessUnlessGranted( 'ez:sqli_admin:entity_edit_element' );

        $updateSuccessfull = false;

        // Check if class annotation allow modification
        $entity = $this->get( 'sqli_admin_entities' )->getEntity( $fqcn, false );

        if( array_key_exists( 'class', $entity ) && array_key_exists( 'annotation', $entity['class'] ) )
        {
            $entityAnnotation = $entity['class']['annotation'];
            // Check if annotation exists
            if( $entityAnnotation instanceof Entity )
            {
                // Check if modification is allowed
                if( $entityAnnotation->isUpdate() )
                {
                    // Try to decode compound Id
                    $compound_id = json_decode( $compound_id, true );

                    // If valid compound Id, update element
                    if( !empty( $compound_id ) )
                    {
                        // Find element
                        $element = $this->get( 'sqli_admin_entities' )->findOneBy( $fqcn, $compound_id );

                        // Build form according to element and entity informations
                        $form = $this->createForm( EditElementType::class, $element, [ 'entity' => $entity ] );
                        $form->handleRequest( $request );

                        if( $form->isSubmitted() && $form->isValid() )
                        {
                            // Form is valid, update element
                            $this->get( 'doctrine.orm.entity_manager' )->persist( $element );
                            $this->get( 'doctrine.orm.entity_manager' )->flush();

                            $updateSuccessfull = true;
                        }
                        else
                        {
                            // Display form
                            $params['form']  = $form->createView();
                            $params['fqcn']  = $fqcn;
                            $params['class'] = $entity['class'];

                            return $this
                                ->render( 'SQLIEzToolboxBundle:Entities:editElement.html.twig',
                                          $params );
                        }
                    }
                }
            }
        }

        if( $updateSuccessfull )
        {
            // Display success notification
            $this
                ->get( 'EzSystems\EzPlatformAdminUi\Notification\FlashBagNotificationHandler' )
                ->success( $this
                               ->get( 'translator' )
                               ->trans( 'entity.element.updated', [], 'sqli_admin' ) );
        }
        else
        {
            // Display error notification
            $this
                ->get( 'EzSystems\EzPlatformAdminUi\Notification\FlashBagNotificationHandler' )
                ->success( $this
                               ->get( 'translator' )
                               ->trans( 'entity.element.cannot_update', [], 'sqli_admin' ) );
        }

        // Redirect to entity homepage (list of elements)
        return $this->redirectToRoute( 'sqli_eztoolbox_entitymanager_entity_homepage',
                                       [ 'fqcn' => $fqcn ] );
    }

    /**
     * Show edit form and save modifications
     *
     * @param string  $fqcn FQCN
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws ReflectionException
     */
    public function createElementAction( $fqcn, Request $request )
    {
        $this->denyAccessUnlessGranted( 'ez:sqli_admin:entity_edit_element' );

        $updateSuccessfull = false;

        // Check if class annotation allow modification
        $entity = $this->get( 'sqli_admin_entities' )->getEntity( $fqcn, false );

        if( array_key_exists( 'class', $entity ) && array_key_exists( 'annotation', $entity['class'] ) )
        {
            $entityAnnotation = $entity['class']['annotation'];
            // Check if annotation exists
            if( $entityAnnotation instanceof Entity )
            {
                // Check if modification is allowed
                if( $entityAnnotation->isUpdate() )
                {
                    // New element
                    $element = new $fqcn();

                    // Build form according to element and entity informations
                    $form = $this->createForm( EditElementType::class, $element, [ 'entity' => $entity ] );
                    $form->handleRequest( $request );

                    if( $form->isSubmitted() && $form->isValid() )
                    {
                        // Form is valid, update element
                        $this->get( 'doctrine.orm.entity_manager' )->persist( $element );
                        $this->get( 'doctrine.orm.entity_manager' )->flush();

                        $updateSuccessfull = true;
                    }
                    else
                    {
                        // Display form
                        $params['form']    = $form->createView();
                        $params['fqcn']    = $fqcn;
                        $params['tabname'] = $entityAnnotation->getTabname();

                        return $this
                            ->render( 'SQLIEzToolboxBundle:Entities:createElement.html.twig',
                                      $params );
                    }
                }
            }
        }

        if( $updateSuccessfull )
        {
            // Display success notification
            $this
                ->get( 'EzSystems\EzPlatformAdminUi\Notification\FlashBagNotificationHandler' )
                ->success( $this
                               ->get( 'translator' )
                               ->trans( 'entity.element.created', [], 'sqli_admin' ) );
        }
        else
        {
            // Display error notification
            $this
                ->get( 'EzSystems\EzPlatformAdminUi\Notification\FlashBagNotificationHandler' )
                ->success( $this
                               ->get( 'translator' )
                               ->trans( 'entity.element.cannot_create', [], 'sqli_admin' ) );
        }

        // Redirect to entity homepage (list of elements)
        return $this->redirectToRoute( 'sqli_eztoolbox_entitymanager_entity_homepage',
                                       [ 'fqcn' => $fqcn ] );
    }

    public function exportCSVAction( $fqcn )
    {
        $this->denyAccessUnlessGranted( 'ez:sqli_admin:entity_export_csv' );

        $response = new StreamedResponse();

        // Check if class annotation allow CSV export
        $entity = $this->get( 'sqli_admin_entities' )->getEntity( $fqcn, false );

        if( array_key_exists( 'class', $entity ) && array_key_exists( 'annotation', $entity['class'] ) )
        {
            $entityAnnotation = $entity['class']['annotation'];
            // Check if annotation exists
            if( $entityAnnotation instanceof Entity )
            {
                // Check if CSV exportation is allowed
                if( $entityAnnotation->isCSVExportable() )
                {
                    // Find element
                    $entityInformations = $this->get( 'sqli_admin_entities' )->getEntity( $fqcn, true );

                    $response->setCallback( function() use ( $entityInformations )
                    {
                        // Open buffer
                        $resource = fopen( 'php://output', 'w+' );

                        $columns = [];
                        foreach( $entityInformations['class']['properties'] as $property_name => $property_infos )
                        {
                            if( $property_infos['visible'] )
                            {
                                $columns[] = $property_name;
                            }
                        }

                        // Add CSV headers
                        fputcsv( $resource, $columns );

                        // Add datas
                        foreach( $entityInformations['elements'] as $element )
                        {
                            $elementDatas = [];
                            // Get value for each column
                            foreach( $columns as $column )
                            {
                                $elementDatas[] = $this->get( 'sqli_admin_entities' )->attributeValue( $element, $column );
                            }
                            // Add line
                            fputcsv( $resource, $elementDatas );
                        }

                        // Close buffer
                        fclose( $resource );
                    } );
                }
            }
        }
        $filename = str_replace( "\\", "_", $fqcn );

        $response->setStatusCode( 200 );
        $response->headers->set( 'Content-Type', 'text/csv; charset=utf-8' );
        $response->headers->set( 'Content-Disposition', "attachment; filename=\"export-{$filename}.csv\"" );

        return $response;
    }
}
