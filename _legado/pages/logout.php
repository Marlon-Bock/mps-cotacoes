<?php
/**
 * Encerra a sessão — POST /logout
 */

auth_logout();
redirect('/login');
