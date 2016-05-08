<?php

namespace CedricZiel\TextmediaMigration\Controller;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Module\AbstractFunctionModule;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Info module function to migrate content elements
 */
class TextmediaMigrationController extends AbstractFunctionModule
{
    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var StandaloneView
     */
    protected $view;

    /**
     * @var string
     */
    protected $moduleName = 'web_info';

    /**
     * @var int
     */
    protected $id;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);

        $this->view = $this->getFluidTemplateObject();
    }

    /**
     * Main function of class
     *
     * @return string HTML output
     */
    public function main()
    {
        $this->id = $pageId = (int)(GeneralUtility::_GP('id'));
        $migrateUid = (int)(GeneralUtility::_GP('migrate_uid'));
        $migratePid = (int)(GeneralUtility::_GP('migrate_pid'));

        if ($migrateUid !== null && $migrateUid !== 0) {
            $this->migrateCe($migrateUid);
        }

        if ($migratePid !== null && $migratePid !== 0) {
            $this->migrateAllCes($migratePid);
        }

        $records = $this->findRecords($pageId);

        $this->view->assign('records', $records);
        $this->view->assign('migrate_pid_link', BackendUtility::getModuleUrl(
            $this->moduleName,
            [
                'id' => $this->id,
                'migrate_pid' => $this->id,
                'SET' => [
                    'function' => static::class,
                ],
            ]
        ));

        return $this->view->render();
    }

    /**
     * Returns the current BE user.
     *
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * returns a new standalone view, shorthand function
     *
     * @return StandaloneView
     */
    protected function getFluidTemplateObject()
    {
        /** @var StandaloneView $view */
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setLayoutRootPaths(array(GeneralUtility::getFileAbsFileName('EXT:textmedia_migration/Resources/Private/Layouts')));
        $view->setPartialRootPaths(array(GeneralUtility::getFileAbsFileName('EXT:textmedia_migration/Resources/Private/Partials')));
        $view->setTemplateRootPaths(array(GeneralUtility::getFileAbsFileName('EXT:textmedia_migration/Resources/Private/Templates')));
        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName('EXT:textmedia_migration/Resources/Private/Templates/Main.html'));
        $view->getRequest()->setControllerExtensionName('textmedia_migration');

        return $view;
    }

    /**
     * Migrates a single element
     *
     * @param int $uid
     */
    protected function migrateCe($uid)
    {
        $uid = (int)$uid;
        if ($uid === 0) {
            return;
        }

        $this->migrateMediaToAssetsForUid($uid);
        $this->migrateCtypesToTextMedia($uid);
        $this->migratePermissions();
    }

    /**
     * Migrates all content elements on the given pid
     *
     * @param int $pid
     */
    protected function migrateAllCes($pid)
    {
        $records = BackendUtility::getRecordsByField(
            'tt_content',
            'pid', $pid,
            'AND CType IN (\'text\', \'image\', \'textpic\')'
        );
        if (is_array($records)) {
            foreach ($records as $record) {
                $this->migrateCe($record['uid']);
            }
        }
    }

    /**
     * @param int $uid
     */
    private function migrateMediaToAssetsForUid($uid)
    {
        $databaseConnection = $this->getDatabaseConnection();
        // Update 'textmedia'
        $query = '
			UPDATE sys_file_reference
			LEFT JOIN tt_content
			ON sys_file_reference.uid_foreign = tt_content.uid
			AND sys_file_reference.tablenames =\'tt_content\'
			AND sys_file_reference.fieldname = \'media\'
			SET tt_content.assets = tt_content.media,
			tt_content.media = 0,
			sys_file_reference.fieldname = \'assets\'
			WHERE
			tt_content.pid = ' . $databaseConnection->fullQuoteStr($uid, 'tt_content') . '
			AND tt_content.CType = \'textmedia\'
			AND tt_content.media > 0
		';
        $databaseConnection->sql_query($query);
    }

    /**
     * @param int $uid
     */
    private function migrateCtypesToTextMedia($uid)
    {
        $databaseConnection = $this->getDatabaseConnection();

        // Update 'text' records
        $databaseConnection->exec_UPDATEquery(
            'tt_content',
            'tt_content.uid=' . $databaseConnection->fullQuoteStr($uid,
                'tt_content') . ' AND tt_content.CType=' . $databaseConnection->fullQuoteStr('text', 'tt_content'),
            [
                'CType' => 'textmedia',
            ]
        );

        // Update 'textpic' and 'image' records
        $query = '
            UPDATE tt_content
            LEFT JOIN sys_file_reference
            ON sys_file_reference.uid_foreign=tt_content.uid
            AND sys_file_reference.tablenames=' . $databaseConnection->fullQuoteStr('tt_content', 'sys_file_reference')
            . ' AND sys_file_reference.fieldname=' . $databaseConnection->fullQuoteStr('image', 'sys_file_reference')
            . ' SET tt_content.CType=' . $databaseConnection->fullQuoteStr('textmedia', 'tt_content')
            . ', tt_content.assets=tt_content.image,
            tt_content.image=0,
            sys_file_reference.fieldname=' . $databaseConnection->fullQuoteStr('assets', 'tt_content')
            . ' WHERE
            tt_content.uid=' . $databaseConnection->fullQuoteStr($uid, 'tt_content') . '
            AND (tt_content.CType=' . $databaseConnection->fullQuoteStr('textpic', 'tt_content')
            . ' OR tt_content.CType=' . $databaseConnection->fullQuoteStr('image', 'tt_content') . ')';
        $databaseConnection->sql_query($query);
    }

    private function migratePermissions()
    {
        $databaseConnection = $this->getDatabaseConnection();

        // Update explicitDeny - ALLOW
        $databaseConnection->exec_UPDATEquery(
            'be_groups',
            '(explicit_allowdeny LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:textpic:ALLOW',
                    'tt_content') . '%', 'tt_content')
            . ' OR explicit_allowdeny LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:image:ALLOW',
                    'tt_content') . '%', 'tt_content')
            . ' OR explicit_allowdeny LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:text:ALLOW',
                    'tt_content') . '%', 'tt_content')
            . ') AND explicit_allowdeny NOT LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:textmedia:ALLOW',
                    'tt_content') . '%', 'tt_content'),
            [
                'explicit_allowdeny' => 'CONCAT(explicit_allowdeny,' . $databaseConnection->fullQuoteStr(',tt_content:CType:textmedia:ALLOW',
                        'tt_content') . ')',
            ],
            [
                'explicit_allowdeny',
            ]
        );

        // Update explicitDeny - DENY
        $databaseConnection->exec_UPDATEquery(
            'be_groups',
            '(explicit_allowdeny LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:textpic:DENY',
                    'tt_content') . '%', 'tt_content')
            . ' OR explicit_allowdeny LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:image:DENY',
                    'tt_content') . '%', 'tt_content')
            . ' OR explicit_allowdeny LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:text:DENY',
                    'tt_content') . '%', 'tt_content')
            . ') AND explicit_allowdeny NOT LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:textmedia:DENY',
                    'tt_content') . '%', 'tt_content'),
            [
                'explicit_allowdeny' => 'CONCAT(explicit_allowdeny,' . $databaseConnection->fullQuoteStr(',tt_content:CType:textmedia:DENY',
                        'tt_content') . ')',
            ],
            [
                'explicit_allowdeny',
            ]
        );
    }

    /**
     * @param $pageId
     * @return array
     */
    protected function findRecords($pageId)
    {
        $records = BackendUtility::getRecordsByField(
            'tt_content',
            'pid', $pageId,
            'AND CType IN (\'text\', \'image\', \'textpic\')'
        );

        if (isset($records) && $records !== null && is_array($records)) {
            foreach ($records as $idx => $record) {
                $icon = $this->iconFactory->getIconForRecord('tt_content', $record, Icon::SIZE_SMALL);
                $records[$idx]['icon_title'] = BackendUtility::wrapClickMenuOnIcon($icon, 'tt_content', $record['uid'])
                    . ' ' . htmlspecialchars(BackendUtility::getRecordTitle('tt_content', $record));
                $records[$idx]['convert_url'] = BackendUtility::getModuleUrl(
                    $this->moduleName,
                    [
                        'id' => $this->id,
                        'migrate_uid' => $record['uid'],
                        'SET' => [
                            'function' => static::class,
                        ],
                    ]
                );
            }

            return $records;
        }

        return [];
    }
}
