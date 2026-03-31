/**
 * Universal Alpine Initialization Helper
 * Fixes race condition where inline scripts run before Alpine.js loads from CDN
 * 
 * Usage in any page:
 * 
 * <script>
 * function myPageInit() {
 *     // Your initialization code here
 *     var main = document.querySelector('main[x-data="myComponent()"]');
 *     if (main && !main._x_dataStack) {
 *         Alpine.initTree(main);
 *     }
 * }
 * 
 * // Use the helper
 * printflowWaitForAlpine(myPageInit);
 * </script>
 */

(function() {
    'use strict';
    
    /**
     * Wait for Alpine.js to load, then execute callback
     * Retries every 50ms until Alpine is ready or timeout (5 seconds)
     * 
     * @param {Function} callback - Function to execute once Alpine is ready
     * @param {number} maxRetries - Maximum number of retries (default: 100 = 5 seconds)
     */
    window.printflowWaitForAlpine = function(callback, maxRetries) {
        if (typeof maxRetries === 'undefined') maxRetries = 100;
        
        var retryCount = 0;
        
        function tryInit() {
            retryCount++;
            
            // Check if Alpine is loaded and ready
            if (typeof window.Alpine !== 'undefined' && 
                typeof Alpine.version !== 'undefined' && 
                typeof Alpine.initTree === 'function') {
                

                
                // Execute callback
                try {
                    callback();
                } catch (e) {

                }
                return;
            }
            
            // Check if we've exceeded max retries
            if (retryCount >= maxRetries) {

                // Try to execute anyway in case Alpine is partially loaded
                try {
                    callback();
                } catch (e) {

                }
                return;
            }
            
            // Retry after 50ms
            setTimeout(tryInit, 50);
        }
        
        // Start trying
        tryInit();
    };
    
    /**
     * Initialize Alpine component with retry logic
     * Automatically waits for Alpine to load
     * 
     * @param {string} selector - CSS selector for the component (e.g., 'main[x-data="myComponent()"]')
     * @param {Function} additionalInit - Optional additional initialization function
     */
    window.printflowInitAlpineComponent = function(selector, additionalInit) {
        printflowWaitForAlpine(function() {
            // Initialize the main component
            var element = document.querySelector(selector);
            if (element && !element._x_dataStack) {
                try {
                    Alpine.initTree(element);

                } catch (e) {

                }
            }
            
            // Run additional initialization if provided
            if (typeof additionalInit === 'function') {
                try {
                    additionalInit();
                } catch (e) {

                }
            }
        });
    };
    

})();
