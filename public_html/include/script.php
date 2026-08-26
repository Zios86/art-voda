<!-- Общие скрипты; Метрика загружается из consent.js только после согласия посетителя. -->
<?php $scriptVersion = $assetVersion ?? '20260816-2'; ?>
<script src="/js/consent.js?v=<?= $scriptVersion ?>" defer></script>
<script src="/js/external-content.js?v=<?= $scriptVersion ?>" defer></script>
<script src="/js/function.js?v=<?= $scriptVersion ?>" defer></script>
