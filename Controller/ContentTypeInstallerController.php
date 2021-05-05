<?php
/**
 * Created by PhpStorm.
 * User: yroux
 * Date: 28/04/2016
 * Time: 08:38
 */

namespace SQLI\EzToolboxBundle\Controller;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\Core\MVC\Symfony\Locale\LocaleConverterInterface;
use EzSystems\EzPlatformAdminUiBundle\Controller\Controller as BaseController;
use SQLI\EzToolboxBundle\Services\ExtractHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class ContentTypeInstallerController extends BaseController
{
    public function listAction(
        ContentTypeService $contentTypeService,
        LocaleConverterInterface $localeConverter,
        RequestStack $requestStack
    ) {
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
        $postVariables         = $_POST;
        $aExportedIds          = $postVariables['ExportIDArray'];
        $aExportedContentTypes = $extractHelper->createContentToExport( $aExportedIds );

        $sResponseContent = "";
        foreach( $aExportedContentTypes as $sExportedContentType )
        {
            $sResponseContent .= $sExportedContentType;
        }

        $headers  =
            [
                'Content-Type'        => 'force-download',
                'Content-Disposition' => 'attachment; filename="contentType.yml"',
                'Pragma'              => 'no-cache',
                'Expires'             => '0',
            ];
        $response = new Response();
        $response->headers->add( $headers );
        $response->setContent( $sResponseContent );
        $response->send();

        return $this->redirectToRoute( "sqli_eztoolbox_contenttype_installer_list" );
    }

    public function indexAction( $name )
    {
        return $this->render( '@SQLIEzToolbox/ContentTypeInstaller/index.html.twig', array( 'name' => $name ) );
    }
}
