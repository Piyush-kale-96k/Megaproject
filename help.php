<?php
// help.php (Content Fragment)
// No <html>, <head>, or <body> tags here. Just the content.
?>
<div class="container mx-auto p-4 sm:p-6">
    <div class="max-w-4xl mx-auto bg-white p-8 shadow-sm rounded-lg border border-gray-200">
        <h1 class="text-3xl font-bold text-slate-800 text-center mb-8">Help & Support</h1>

        <div class="space-y-8">
            <!-- FAQ Item 1 -->
            <div class="border-b border-gray-100 pb-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-2 flex items-center">
                    <span class="bg-blue-100 text-blue-600 w-8 h-8 flex items-center justify-center rounded-full text-sm mr-3">1</span>
                    I forgot my password. What should I do?
                </h3>
                <p class="text-gray-600 ml-11">Use the "Forgot Password" option on the login page. You will be asked to enter your email address to receive a password reset link.</p>
            </div>

            <!-- FAQ Item 2 -->
            <div class="border-b border-gray-100 pb-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-2 flex items-center">
                     <span class="bg-blue-100 text-blue-600 w-8 h-8 flex items-center justify-center rounded-full text-sm mr-3">2</span>
                    How do I edit my profile?
                </h3>
                <p class="text-gray-600 ml-11">Navigate to the "Profile" page from the sidebar. Click the "Edit Profile" button, update your details, and click "Save Changes".</p>
            </div>

            <!-- FAQ Item 3 -->
            <div class="border-b border-gray-100 pb-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-2 flex items-center">
                     <span class="bg-blue-100 text-blue-600 w-8 h-8 flex items-center justify-center rounded-full text-sm mr-3">3</span>
                    How do I report a problem with a PC?
                </h3>
                <p class="text-gray-600 ml-11">Click the "Report Fault" button in the sidebar. Select the Lab, enter the PC Number, choose the category of the issue (Hardware, Software, etc.), and provide a description.</p>
            </div>

            <!-- FAQ Item 4 -->
            <div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2 flex items-center">
                     <span class="bg-blue-100 text-blue-600 w-8 h-8 flex items-center justify-center rounded-full text-sm mr-3">4</span>
                    How do I send a message?
                </h3>
                <p class="text-gray-600 ml-11">Go to the "Contact" page, fill in your Name, Email, and Message in the provided form, then click the "Send Message" button.</p>
            </div>
        </div>

        <!-- Footer Link -->
        <div class="mt-10 pt-6 border-t border-gray-200 text-center">
            <p class="text-gray-600">Still need help? <a href="?page=contact" class="text-blue-600 font-semibold hover:underline">Contact Support</a></p>
        </div>
    </div>
</div>