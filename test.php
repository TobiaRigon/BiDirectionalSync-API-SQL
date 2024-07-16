<?php
if (extension_loaded('sqlsrv')) {
    echo "L'estensione sqlsrv è caricata.\n";
} else {
    echo "L'estensione sqlsrv non è caricata.\n";
}

if (extension_loaded('pdo_sqlsrv')) {
    echo "L'estensione pdo_sqlsrv è caricata.\n";
} else {
    echo "L'estensione pdo_sqlsrv non è caricata.\n";
}
