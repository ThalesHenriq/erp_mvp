<?php
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function redirectWithMessage($page, $type, $message) {
    $_SESSION[$type] = $message;
    header("Location: " . BASE_URL . "/$page");
    exit();
}

function displayFlashMessages() {
    if (isset($_SESSION['sucesso'])) {
        echo '<div class="sucesso">' . $_SESSION['sucesso'] . '</div>';
        unset($_SESSION['sucesso']);
    }
    if (isset($_SESSION['erro'])) {
        echo '<div class="erro">' . $_SESSION['erro'] . '</div>';
        unset($_SESSION['erro']);
    }
}
?>