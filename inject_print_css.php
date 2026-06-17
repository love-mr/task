<?php
$dashboardPath = __DIR__ . '/dashboard.php';
$content = file_get_contents($dashboardPath);

$printCss = <<<'CSS'
<style type="text/css" media="print">
    /* Hide everything by default on print */
    body * {
        visibility: hidden;
    }
    
    /* Show only the Survey Management table and its contents */
    #view-surveymanagement, #view-surveymanagement * {
        visibility: visible;
    }
    
    /* Position the Survey Management view at the top of the print page */
    #view-surveymanagement {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }

    /* Hide buttons and filters inside the survey management view so they don't print */
    #view-surveymanagement .rsk-module-header button,
    #view-surveymanagement .rsk-action-bar,
    #view-surveymanagement td button,
    #view-surveymanagement .action-buttons {
        display: none !important;
    }
    
    /* Clean up the table appearance for print */
    #view-surveymanagement table {
        width: 100%;
        border-collapse: collapse;
    }
    #view-surveymanagement th, #view-surveymanagement td {
        border: 1px solid #ccc;
        padding: 8px;
    }
</style>
CSS;

if (strpos($content, 'media="print"') === false) {
    $content = str_replace('</head>', $printCss . "\n</head>", $content);
    file_put_contents($dashboardPath, $content);
    echo "Print CSS injected.\n";
} else {
    echo "Print CSS already exists.\n";
}
