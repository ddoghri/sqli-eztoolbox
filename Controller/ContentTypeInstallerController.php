<?php
/**
 * Created by PhpStorm.
 * User: yroux
 * Date: 28/04/2016
 * Time: 08:38
 */

namespace SQLI\EzToolboxBundle\Controller;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\Core\MVC\Symfony\Locale\LocaleConverter;
use EzSystems\EzPlatformAdminUiBundle\Controller\Controller as BaseController;
use SQLI\EzToolboxBundle\Services\ExtractHelper;
use Symfony\Component\HttpFoundation\RequestStack;

class ContentTypeInstallerController extends BaseController
{
    public function listAction( ContentTypeService $contentTypeService, LocaleConverter $localeConverter,
                                RequestStack $requestStack )
    {
        $locale = $requestStack->getCurrentRequest()->getLocale();

        //Retrieve all the groups
        $aGroups       = $contentTypeService->loadContentTypeGroups();
        $aContentTypes = [];
        foreach( $aGroups as $group )
        {
            $aContentTypes[$group->identifier] = $contentTypeService->loadContentTypes( $group );
        }

        return $this->render( '@SQLIEzToolbox/ContentTypeInstaller/list.html.twig', [
            'aContentTypes' => $aContentTypes,
            'language'      => $localeConverter->convertToEz( $locale )
        ] );
    }

    public function exportAction( ExtractHelper $extractHelper )
    {
        $postVariables = $_POST;
        $aExportedIds  = $postVariables['ExportIDArray'];
        $content       = $extractHelper->createContentToExport( $aExportedIds );

        header( 'Content-Type: force-download' );
        header( 'Content-Disposition: attachment; filename="contentType.yml"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        echo $content;
        exit( 0 );
    }

    public function indexAction( $name )
    {
        return $this->render( '@SQLIEzToolbox/ContentTypeInstaller/index.html.twig', array( 'name' => $name ) );
    }
}
