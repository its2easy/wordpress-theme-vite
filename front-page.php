<?php
get_header();
?>
<section>
    <div class="container">
        <h1>Frontpage template</h1>
        <button type="button" class="btn btn-primary js__open-modal">Check modal</button>
        <button type="button" class="btn btn-primary js__dynamic-import">Check dynamic import</button>
        <button type="button" class="btn btn-info check-autoprefixer">
            Check autoprefixer working for this element (in devtools)
        </button>

        <div class="test-group">
            <h2 class="test-group__title">Checks for current page (css):</h2>
            <div class="check-item check-main-css">main entrypoint:</div>
            <div class="check-item check-css-only-entry">css-only entrypoint:</div>
            <div class="check-item check-frontpage-entry">frontpage entrypoint:</div>
            <div class="check-item check-component-1">example component 1:</div>
            <div class="check-item check-component-2">example component 2:</div>
            <div class="check-bs">
                <span>bootstrap styles:</span>
                <div class="alert alert-success mb-0 check-bs-alert" role="alert">success alert!</div>
            </div>
        </div>

        <div class="test-group">
            <h2 class="test-group__title">Checks NOT for current page (css):</h2>
            <div class="check-item check-page-1-entry">page 1 entrypoint:</div>
            <div class="check-item check-page-2-entry">page 2 entrypoint (js-only):</div>
        </div>

    </div>
</section>
<?php
get_footer();
