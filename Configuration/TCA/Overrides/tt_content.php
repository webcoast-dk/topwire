<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addTCAcolumns(
    'tt_content',
    [
        'tx_turbo_wrap_in_frame' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:topwire/Resources/Private/Language/locallang_db.xlf:tt_content.tx_turbo_wrap_in_frame.label',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'tx_turbo_frame_id' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:topwire/Resources/Private/Language/locallang_db.xlf:tt_content.tx_turbo_frame_id.label',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ]
    ]
);

ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    '--div--;LLL:EXT:topwire/Resources/Private/Language/locallang_db.xlf:tt_content.tabs.topwire.title, tx_turbo_wrap_in_frame, tx_turbo_frame_id',
);
