// debug_buttons.js - Debug tool for button click issues
(function() {
    console.log('🔧 TALENTRIX Debug Tool Loaded');
    
    // Check if document is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDebug);
    } else {
        initDebug();
    }
    
    function initDebug() {
        console.log('📊 DOM fully loaded');
        
        // Check for registration form
        const regForm = document.getElementById('registrationForm');
        if (regForm) {
            console.log('✅ Registration form found');
            
            // Check submit button
            const submitBtn = document.getElementById('submit-btn') || 
                             document.querySelector('.btn-signup') ||
                             document.querySelector('button[type="submit"]');
            
            if (submitBtn) {
                console.log('✅ Submit button found:', submitBtn);
                
                // Check if button is disabled
                if (submitBtn.disabled) {
                    console.warn('⚠️ Submit button is disabled');
                }
                
                // Check click event
                submitBtn.addEventListener('click', function(e) {
                    console.log('🖱️ Submit button clicked');
                    
                    // Check form validity
                    if (regForm.checkValidity) {
                        if (!regForm.checkValidity()) {
                            console.warn('⚠️ Form is invalid');
                            
                            // Find invalid fields
                            const invalidFields = regForm.querySelectorAll(':invalid');
                            console.log('Invalid fields:', invalidFields.length);
                            invalidFields.forEach(field => {
                                console.log('  -', field.name || field.id, ':', field.validationMessage);
                            });
                        } else {
                            console.log('✅ Form is valid');
                        }
                    }
                });
            } else {
                console.error('❌ Submit button not found');
            }
            
            // Check for JavaScript errors
            window.onerror = function(msg, url, lineNo, columnNo, error) {
                console.error('❌ JavaScript Error:', msg, 'at', lineNo);
                return false;
            };
            
            // Monitor form submission
            regForm.addEventListener('submit', function(e) {
                console.log('📤 Form submission triggered');
                
                // Check if preventDefault was called
                if (e.defaultPrevented) {
                    console.warn('⚠️ Form submission was prevented');
                }
            });
        } else {
            console.log('ℹ️ Not on registration page');
        }
        
        // Check for user type selector
        const userTypeOptions = document.querySelectorAll('.type-option');
        if (userTypeOptions.length > 0) {
            console.log('✅ User type selector found with', userTypeOptions.length, 'options');
            
            userTypeOptions.forEach((option, index) => {
                option.addEventListener('click', function(e) {
                    console.log('🖱️ User type clicked:', this.querySelector('.type-label')?.textContent || index);
                });
            });
        }
        
        // Check for student type selector
        const studentTypeOptions = document.querySelectorAll('.student-type-option');
        if (studentTypeOptions.length > 0) {
            console.log('✅ Student type selector found with', studentTypeOptions.length, 'options');
        }
        
        // Log all event listeners (debugging)
        console.log('🔍 Debug information:');
        console.log('  - User Agent:', navigator.userAgent);
        console.log('  - Viewport:', window.innerWidth, 'x', window.innerHeight);
        console.log('  - Form exists:', !!regForm);
        console.log('  - jQuery available:', typeof jQuery !== 'undefined');
        
        // Add visual debug overlay (optional)
        addDebugOverlay();
    }
    
    function addDebugOverlay() {
        // Only add if in development mode (you can remove this in production)
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            const debugDiv = document.createElement('div');
            debugDiv.style.cssText = `
                position: fixed;
                bottom: 10px;
                right: 10px;
                background: rgba(0,0,0,0.8);
                color: #0f0;
                padding: 10px;
                border-radius: 5px;
                font-family: monospace;
                font-size: 12px;
                z-index: 9999;
                cursor: pointer;
            `;
            debugDiv.innerHTML = '🐞 Debug Mode<br>Click to test buttons';
            
            debugDiv.addEventListener('click', function() {
                console.clear();
                console.log('🧪 Running debug tests...');
                
                // Test all buttons
                const buttons = document.querySelectorAll('button, .btn, [type="submit"]');
                console.log(`Found ${buttons.length} buttons/clickable elements`);
                
                buttons.forEach((btn, i) => {
                    console.log(`Button ${i+1}:`, {
                        text: btn.textContent?.trim(),
                        disabled: btn.disabled,
                        type: btn.type,
                        class: btn.className,
                        visible: btn.offsetParent !== null
                    });
                });
            });
            
            document.body.appendChild(debugDiv);
        }
    }
})();