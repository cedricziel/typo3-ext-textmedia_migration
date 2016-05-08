<?php

defined('TYPO3_MODE') or die();

if (TYPO3_MODE === 'BE') {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
        'web_info',
        \CedricZiel\TextmediaMigration\Controller\TextmediaMigrationController::class,
        null,
        'Textmedia Migrator'
    );
}
