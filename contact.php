<?php
// Initialize variables for form feedback
$message_sent = false;
$error_message = '';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve form data
    $name = filter_var(trim($_POST["name"]), FILTER_SANITIZE_STRING);
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $message = filter_var(trim($_POST["message"]), FILTER_SANITIZE_STRING);

    // Validate form data
    if (empty($name) || empty($email) || empty($message)) {
        $error_message = 'Please fill out all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email format.';
    } else {
        // --- Email Configuration ---
        // IMPORTANT: Replace with your email address
        $to = "kalepiyush953@gmail.com"; 
        $subject = "New Contact Form Submission from " . $name;
        $body = "You have received a new message from your website contact form.\n\n";
        $body .= "Name: " . $name . "\n";
        $body .= "Email: " . $email . "\n";
        $body .= "Message:\n" . $message . "\n";
        $headers = "From: no-reply@yourwebsite.com\r\n";
        $headers .= "Reply-To: " . $email . "\r\n";

        // Send the email
        // Note: For this to work, your web server must be configured to send emails.
        if (mail($to, $subject, $body, $headers)) {
            $message_sent = true;
        } else {
            $error_message = 'Sorry, there was an error sending your message. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
  
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
  
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  
    <style>
    /* Darker gray tones */
    .brand-gray { background-color: #4b5563; } /* tailwind gray-600 */
    .brand-gray-hover:hover { background-color: #374151; } /* tailwind gray-700 */
    .brand-gray-text { color: #4b5563; }
    </style>
</head>
<body class="bg-gray-100 font-sans">

<?php
// If included in homepage, parent should set $embedded_in_homepage = true before including.
// When embedded, render a compact fragment (no header / big top padding) so it sits directly under the homepage header.
if (!isset($embedded_in_homepage) || $embedded_in_homepage !== true):
?>

    <!-- Header Section -->
    <header class="bg-white shadow-sm">
        <div class="container mx-auto px-6 py-4">
            <h1 class="text-2xl font-bold text-gray-800">Contact Us</h1>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-10">
        <div class="bg-white p-8 rounded-lg shadow-lg max-w-5xl mx-auto">
                <div class="flex flex-col md:flex-row gap-10">
<?php else: ?>
    <!-- Embedded fragment -->
    <div class="bg-white p-4 rounded-lg shadow-sm w-full">
        <div class="flex flex-col md:flex-row gap-6">
<?php endif; ?>
            <!-- Form Section -->
            <div class="w-full md:w-1/2">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">Send us a Message</h3>
                
                <?php if ($message_sent): ?>
                    <div class="p-4 bg-green-100 text-green-800 border border-green-200 rounded-lg">
                        Thank you! Your message has been sent successfully.
                    </div>
                <?php else: ?>
                    <?php if ($error_message): ?>
                        <div class="p-4 mb-4 bg-red-100 text-red-800 border border-red-200 rounded-lg">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    // When embedded, post to ?page=contact so homepage handles the POST.
                    $form_action = (isset($embedded_in_homepage) && $embedded_in_homepage === true) ? '?page=contact' : htmlspecialchars($_SERVER["PHP_SELF"]);
                    ?>
                    <form action="<?php echo $form_action; ?>" method="post">
                        <div class="mb-4">
                            <label for="name" class="block text-gray-700 text-sm font-semibold mb-2">Name</label>
                            <input type="text" id="name" name="name" placeholder="Enter your name" pattern="[A-Za-z\s]+" class="w-full px-4 py-2 border rounded-lg text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-600" required>
                        </div>
                        <div class="mb-4">
                            <label for="email" class="block text-gray-700 text-sm font-semibold mb-2">Email</label>
                            <input type="email" id="email" name="email" placeholder="you@example.com" class="w-full px-4 py-2 border rounded-lg text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-600" required>
                        </div>
                        <div class="mb-6">
                            <label for="message" class="block text-gray-700 text-sm font-semibold mb-2">Message</label>
                            <textarea id="message" name="message" placeholder="Your message..." rows="5" class="w-full px-4 py-2 border rounded-lg text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-600 resize-none" required></textarea>
                        </div>
                        <button type="submit" class="w-full brand-gray text-white font-bold py-3 px-4 rounded-lg brand-gray-hover transition-colors duration-300">
                            Send Message
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Info Section -->
            <div class="w-full md:w-1/2">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">Contact Information</h3>
                <div class="space-y-6 text-gray-700">
                    <div class="flex items-start">
                        <i class="fas fa-map-marker-alt brand-gray-text text-xl mt-1 w-6 text-center"></i>
                        <div class="ml-4">
                            <h4 class="font-bold">Our Office</h4>
                            <p>CSMSS College of Polytechnic, Paithan Road, Kanchanwadi, Chh. Sambhajinagar, Maharashtra 431005</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-envelope brand-gray-text text-xl w-6 text-center"></i>
                        <div class="ml-4">
                            <h4 class="font-bold">Email Us</h4>
                            <p>csmsscollagepoly@gmail.com</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-phone brand-gray-text text-xl w-6 text-center"></i>
                        <div class="ml-4">
                            <h4 class="font-bold">Call Us</h4>
                            <p>(123) 456-7890</p>
                        </div>
                    </div>
                </div>

                <h3 class="text-xl font-bold text-gray-800 mt-10 mb-4">Follow Us</h3>
                <div class="flex space-x-4">
                    <a href="#" class="text-gray-500 hover:text-gray-600 transition-colors duration-300 text-2xl"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-gray-500 hover:text-gray-600 transition-colors duration-300 text-2xl"><i class="fab fa-linkedin"></i></a>
                    <a href="#" class="text-gray-500 hover:text-gray-600 transition-colors duration-300 text-2xl"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-gray-500 hover:text-gray-600 transition-colors duration-300 text-2xl"><i class="fab fa-facebook"></i></a>
                </div>
            </div>
                </div>
        </div>
<?php
if (!isset($embedded_in_homepage) || $embedded_in_homepage !== true):
?>
    </main>
<?php else: ?>
        </div>
<?php endif; ?>

 <!-- Footer -->
  <!-- <footer class="bg-white mt-10">-->
   <!--  <div class="container mx-auto px-6 py-4 text-center text-gray-600">-->
   <!--    &copy; <span id="currentYear"><?php echo date("Y"); ?></span> All Rights Reserved.-->
   <!--  </div>-->
  <!--  </footer> -->

</body>
</html>
