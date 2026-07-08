( function () {
    // Values injected from PHP via wp_localize_script.
    if ( typeof HeadlessPreviewData === 'undefined' ) {
        return;
    }

    const FRONTEND_URL = HeadlessPreviewData.frontendUrl;
    const SECRET       = HeadlessPreviewData.secret;

    // Rewrites the preview button's link to point to the headless frontend.
    function updatePreviewLink() {
        if ( ! window.wp || ! wp.data ) {
            return;
        }

        const editor = wp.data.select( 'core/editor' );
        if ( ! editor ) {
            return;
        }

        const postId   = editor.getCurrentPostId();
        const postType = editor.getCurrentPostType();
        if ( ! postId || ! postType ) {
            return;
        }

        const previewUrl = FRONTEND_URL + '/api/preview'
            + '?secret=' + encodeURIComponent( SECRET )
            + '&id=' + encodeURIComponent( postId )
            + '&type=' + encodeURIComponent( postType );

        const selectors = [
            '.editor-preview-dropdown__button-external',
            '[aria-label="Preview in new tab"]',
        ];

        selectors.forEach( function ( selector ) {
            document.querySelectorAll( selector ).forEach( function ( link ) {
                if ( link.getAttribute( 'href' ) !== previewUrl ) {
                    link.setAttribute( 'href', previewUrl );
                    link.setAttribute( 'target', '_blank' );
                }
            } );
        } );
    }

    // Wait until wp.data is ready, then listen for editor changes.
    const ready = setInterval( function () {
        if ( window.wp && wp.data ) {
            clearInterval( ready );
            wp.data.subscribe( updatePreviewLink );
            updatePreviewLink();
        }
    }, 500 );
} )();