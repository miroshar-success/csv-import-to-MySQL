<?php
session_start();

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "admin123";
$database = "csvimport";

$conn = new mysqli($servername, $username, $password, $database);

define('PAGINATION_DISPLAY_COUNT', 2); // Number of pages to display before and after the current page

$result1 = [];
$row = [];
$current_page = 1;
$total_pages = 1;
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// File upload handling
if(isset($_POST['submit'])){
    $file = $_FILES['file']['tmp_name'];
    $fileType = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);

    if ($fileType === 'csv') {
        if (($handle = fopen($file, "r")) !== FALSE) {
            fgetcsv($handle); // Skip the first row (column headers)
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Sanitize input data
                $data = array_map(function($value) use ($conn) {
                    return mysqli_real_escape_string($conn, $value);
                }, $data);
                $sql = "INSERT INTO indirrizzi (modello, codiceUbicazione, ubicazione, indirizzo, comune, provincia, regione, modelloAAMS) VALUES ('$data[0]', '$data[1]', '$data[2]', '$data[3]', '$data[4]', '$data[5]', '$data[6]', '$data[7]')";
                $conn->query($sql);
            }
            fclose($handle);
        }
    } elseif ($fileType === 'xlsm' || $fileType === 'ods') {
        require 'vendor/autoload.php';
        $conn->query("DELETE FROM `indirrizzi`"); 
        $conn->query("DELETE FROM `ciclo`"); 
        $conn->query("DELETE FROM `fineciclo`"); 
        $conn->query("DELETE FROM `parc`"); 
        $conn->query("DELETE FROM `provincia`"); 
        $conn->query("DELETE FROM `lotto`"); 

        $meta = [
            [
                "db" => [
                    "table" => 'indirrizzi',
                    "columns" => [
                        "gestore", "mac_address", "num_awp", "identificativo", "modello", "codiceUbicazione", "ubicazione", "indirizzo", "comune", "provincia", "regione", "modelloAAMS"
                    ],
                ],
                "sheet" => [
                    "index" => 0,
                    "formula" => false,
                    "start_row" => 3,
                    "start_col" => "F",
                    "columns" => [
                        0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11
                    ]
                ]
            ],
            [
                "db" => [
                    "table" => 'fineciclo',
                    "columns" => [
                        "colA", "colB", "colC"
                    ],
                ],
                "sheet" => [
                    "index" => 1,
                    "formula" => false,
                    "start_row" => 2,
                    "start_col" => "A",
                    "columns" => [
                        0, 1, 6
                    ]
                ]
            ],
            [
                "db" => [
                    "table" => 'parc',
                    "columns" => [
                        "cod_gestore", "gestore", "codid", "codid_provv", "vincite", "software_house", "titolo", 'stato', 'pda', "codice_ubicazione", "ubicazione", "ultima_lett", "ultimo_contatto", "ult_cnttotin", "ult_cnttotot", "processo_in_corso", "gg_decad_noe", "gg_manut_str"
                    ],
                ],
                "sheet" => [
                    "index" => 2,
                    "formula" => false,
                    "start_row" => 3,
                    "start_col" => "A",
                    "columns" => [
                        0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17
                    ]
                ]
            ],
            [
                "db" => [
                    "table" => 'lotto',
                    "columns" => [
                        "codice_awp", "fineciclo"
                    ],
                ],
                "sheet" => [
                    "index" => 3,
                    "formula" => true,
                    "start_row" => 4,
                    "start_col" => "A",
                    "columns" => [
                        "A", "S"
                    ]
                ]
            ],
            [
                "db" => [
                    "table" => 'provincia',
                    "columns" => [
                        "colA", "colB"
                    ],
                ],
                "sheet" => [
                    "index" => 4,
                    "formula" => false,
                    "start_row" => 1,
                    "start_col" => "A",
                    "columns" => [
                        0, 1
                    ]
                ]
            ],
            [
                "db" => [
                    "table" => 'ciclo',
                    "columns" => [
                        "colA", "colB", "colC", "colD", "colE", "colF", "colG", "colH", "colI", "colJ"
                    ],
                ],
                "sheet" => [
                    "index" => 5,
                    "formula" => false,
                    "start_row" => 3,
                    "start_col" => "A",
                    "columns" => [
                        0, 1, 2, 3, 4, 5, 6, 7, 8, 9
                    ]
                ]
            ],
        ];
        
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);

        foreach ($meta as $idx => $e) {
            
            if ($e["sheet"]["formula"] == false) {
                // Set the active sheet based on the index specified in metadata
                $sheet = $spreadsheet->setActiveSheetIndex($e["sheet"]["index"]);
    
                // Get the highest column and row numbers in the sheet
                $maxCol = $sheet->getHighestColumn();
                $maxRow = $sheet->getHighestRow();
    
                // Generate the range string for fetching data
                $range = $e['sheet']['start_col'] . $e['sheet']['start_row'] . ":{$maxCol}{$maxRow}";
    
                // Fetch the data within the specified range as an array
                $data = $sheet->rangeToArray($range, null, true, true, false, true);
            }
            else {
                $sheet = $spreadsheet->setActiveSheetIndex($e["sheet"]["index"]);
                $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
                $maxRow = $sheet->getHighestRow();

                $data = [];
                for ($row = $e['sheet']['start_row']; $row <= $maxRow; $row++) {
                    $rowData = [];
                    foreach ($e['sheet']['columns'] as $col) {
                        $colLetter = $col;// \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
                        $value = $sheet->getCell($colLetter . $row)->getValue();
                        // $value = $cell->isFormula() ? $cell->getCalculatedValue() : $cell->getValue();
                        if ($value !== null && $value !== '') {
                            $rowData[] = str_replace("\"", "", $value);
                        }
                    }
                    if (count($rowData) === count($e['sheet']['columns'])) {
                        $data[] = $rowData;
                    }
                }
            }

            $q_rows = [];
            for ($i = 0; $i < count($data); $i++) {
                $row = $data[$i];
                $arr_data = [];
                foreach ($e['sheet']["columns"] as $idx => $col) {
                    $val = str_replace("\"", "", $e["sheet"]["formula"] == false ? $row[$col] : $row[$idx]);
                    array_push($arr_data, "\"{$val}\"");
                }
                array_push($q_rows, "(" . implode(',', $arr_data) . ")");
            }
            $q_inserts = implode(',', $q_rows);

            $arr_db_cols = [];
            foreach ($e['db']["columns"] as $col_name) {
                array_push($arr_db_cols, "`{$col_name}`");
            }
            $q_db_cols = implode(',', $arr_db_cols);

            $tbl = $e['db']["table"];
            $q = "INSERT INTO `$tbl` ($q_db_cols) VALUES $q_inserts";
            $conn->query("DELETE FROM `{$tbl}`");
            $conn->query($q);
        }
        
        $conn->query("UPDATE `lotto` SET
            `denominazione` = (SELECT IFNULL(`ubicazione`, '') FROM `parc` WHERE `parc`.`codid` = `lotto`.`codice_awp` LIMIT 1),
            `ubicazione` = (SELECT IFNULL(`codice_ubicazione`, '') FROM `parc` WHERE `parc`.`codid` = `lotto`.`codice_awp` LIMIT 1),
            `data` = (SELECT IFNULL(`ultima_lett`, '') FROM `parc` WHERE `parc`.`codid` = `lotto`.`codice_awp` LIMIT 1),
            `percent` = (SELECT IFNULL(SUBSTR(`vincite`, 1, 2), '') FROM `parc` WHERE `parc`.`codid` = `lotto`.`codice_awp` LIMIT 1),
            `totin` = (SELECT IFNULL(`ult_cnttotin`, '') FROM `parc` WHERE `parc`.`codid` = `lotto`.`codice_awp` LIMIT 1),
            `totout` = (SELECT IFNULL(`ult_cnttotot`, '') FROM `parc` WHERE `parc`.`codid` = `lotto`.`codice_awp` LIMIT 1),
            `3` = (SELECT IFNULL(`titolo`, '') FROM `parc` WHERE `parc`.`codid` = `lotto`.`codice_awp` LIMIT 1)");

        $conn->query("UPDATE `lotto` SET 
            `indirrizo` = (SELECT IFNULL(`indirizzo`, '') FROM `indirrizzi` WHERE `indirrizzi`.`codiceUbicazione` = `lotto`.`ubicazione` LIMIT 1),
            `comune` = (SELECT IFNULL(`comune`, '') FROM `indirrizzi` WHERE `indirrizzi`.`codiceUbicazione` = `lotto`.`ubicazione` LIMIT 1),
            `provincia` = (SELECT IFNULL(`provincia`, '') FROM `indirrizzi` WHERE `indirrizzi`.`codiceUbicazione` = `lotto`.`ubicazione` LIMIT 1),
            `regione` = (SELECT IFNULL(`regione`, '') FROM `indirrizzi` WHERE `indirrizzi`.`codiceUbicazione` = `lotto`.`ubicazione` LIMIT 1)");

        $conn->query("UPDATE `lotto` SET 
            `ciclo` = (SELECT IFNULL(`colH`, '') FROM `ciclo` WHERE `ciclo`.`colA` = `lotto`.`3` LIMIT 1)");

    }
}

// User login handling
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username='$username' AND `password`='$password'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $_SESSION['username'] = $username;
        header("Location: index.php");
    } else {
        echo "Invalid username or password";
    }
}

if (isset($_POST['signout'])) {
    session_destroy();
    header("Location: index.php");
}

// User registration handling
// if (isset($_POST['register'])) {
//     $username = $_POST['username'];
//     $password = $_POST['password'];

//     // Hash the password before storing it
//     $hashed_password = password_hash($password, PASSWORD_BCRYPT);

//     $sql = "INSERT INTO users (username, password) VALUES ('$username', '$hashed_password')";
//     if ($conn->query($sql) === TRUE) {
//         echo "Registration successful";
//     } else {
//         echo "Error: " . $sql . "<br>" . $conn->error;
//     }
// }

$search = isset($_GET['search']) ? $_GET['search'] : '';
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'codice_awp';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'ASC';
$groupBy = isset($_GET['group_by']) ? $_GET['group_by'] : '';

// Check if search is performed
if ($search !== '') {
    // Perform search query
    $sql = "SELECT * FROM lotto WHERE codice_awp LIKE '%$search%' OR denominazione LIKE '%$search%' OR ubicazione LIKE '%$search%' OR indirrizo LIKE '%$search%' OR comune LIKE '%$search%' OR provincia LIKE '%$search%' OR regione LIKE '%$search%' OR 3 LIKE '%$search%' OR cicloin LIKE '%$search%' OR ciclout LIKE '%$search%' OR data LIKE '%$search%' OR sopra LIKE '%$search%' OR vincita LIKE '%$search%' OR manca LIKE '%$search%' OR ciclo LIKE '%$search%' OR percent LIKE '%$search%' OR totin LIKE '%$search%' OR totout LIKE '%$search%' OR fineciclo LIKE '%$search%' OR ncicli LIKE '%$search%'";
    $result = $conn->query($sql);
    $total_records = $result->num_rows;
    
    $records_per_page = 10;
    $total_pages = ceil($total_records / $records_per_page);
    
    if (isset($_GET['page']) && is_numeric($_GET['page'])) {
        $current_page = (int) $_GET['page'];
    }

    $offset = ($current_page - 1) * $records_per_page;

    
    $orderClause = " ORDER BY ";
    $orderClause .= "$sortColumn $sortOrder";
   
    if (!empty($groupBy)) {
        $sql = "SELECT * FROM lotto WHERE $groupBy LIKE '%$search%' $orderClause LIMIT $offset, $records_per_page";    
    } else {
        $sql = "SELECT * FROM lotto WHERE codice_awp LIKE '%$search%' OR denominazione LIKE '%$search%' OR ubicazione LIKE '%$search%' OR indirrizo LIKE '%$search%' OR comune LIKE '%$search%' OR provincia LIKE '%$search%' OR regione LIKE '%$search%' OR 3 LIKE '%$search%' OR cicloin LIKE '%$search%' OR ciclout LIKE '%$search%' OR data LIKE '%$search%' OR sopra LIKE '%$search%' OR vincita LIKE '%$search%' OR manca LIKE '%$search%' OR ciclo LIKE '%$search%' OR percent LIKE '%$search%' OR totin LIKE '%$search%' OR totout LIKE '%$search%' OR fineciclo LIKE '%$search%' OR ncicli LIKE '%$search%' $orderClause LIMIT $offset, $records_per_page";
    }
    $result1 = $conn->query($sql);
} else {
    // Load initial data without search
    $records_per_page = 10;
    $sql = "SELECT * FROM lotto";
    $result = $conn->query($sql);
    $total_records = $result->num_rows;
    $total_pages = ceil($total_records / $records_per_page);

    if (isset($_GET['page']) && is_numeric($_GET['page'])) {
        $current_page = (int) $_GET['page'];
    }

    $offset = ($current_page - 1) * $records_per_page;

    $orderClause = " ORDER BY ";
    $orderClause .= "$sortColumn $sortOrder";

    if (!empty($groupBy)) {
        $sql = "SELECT * FROM lotto WHERE $groupBy LIKE '%$search%' $orderClause LIMIT $offset, $records_per_page";    
    } else {
        $sql = "SELECT * FROM lotto $orderClause LIMIT $offset, $records_per_page";
    }
    print_r($sql);
    $result1 = $conn->query($sql);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Import Data</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <?php if(!isset($_SESSION['username'])) { ?>
            <div class="row">
                <div class="col-md-6">
                    <h2>Login</h2>
                    <form method="post" action="">
                        <div class="form-group">
                            <input type="text" name="username" class="form-control" placeholder="Username" required>
                        </div>
                        <div class="form-group">
                            <input type="password" name="password" class="form-control" placeholder="Password" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary">Login</button>
                    </form>
                </div>
                <!-- <div class="col-md-6">
                    <h2>Register</h2>
                    <form method="post" action="">
                        <div class="form-group">
                            <input type="text" name="username" class="form-control" placeholder="Username" required>
                        </div>
                        <div class="form-group">
                            <input type="password" name="password" class="form-control" placeholder="Password" required>
                        </div>
                        <button type="submit" name="register" class="btn btn-primary">Register</button>
                    </form>
                </div> -->
            </div>
        <?php } else { ?>
            <form method="post" action="" class="mb-4">
                <div class="input-group">
                    <input type="hidden" name="signout" />
                    <button type="submit"  class="btn btn-primary" style="float: right;">Sign Out</button>
                </div>            
            </form>
            <h3>Upload CSV/XLSM/ODS File</h3>
            <form method="post" action="" enctype="multipart/form-data" class="mb-4">
                <div class="input-group">
                    <div class="custom-file">
                        <input type="file" name="file" class="custom-file-input" id="fileInput">
                        <label class="custom-file-label" for="fileInput">Choose file</label>
                    </div>
                    <div class="input-group-append">
                        <button type="submit" name="submit" class="btn btn-primary">Import</button>
                    </div>
                </div>
            </form>


            <h3>Search Data</h3>
            <form method="get" action="" class="mb-4">
                <div class="input-group">
                    <select name="group_by" class="form-control">
                        <option value="" <?php echo empty($groupBy) ? 'selected' : ''; ?>>All</option>
                        <option value="codice_awp" <?php echo $groupBy == 'codice_awp' ? 'selected' : ''; ?>>CODICE AWP</option>
                        <option value="denominazione" <?php echo $groupBy == 'denominazione' ? 'selected' : ''; ?>>DENOMINAZIONE</option>
                        <option value="ubicazione" <?php echo $groupBy == 'ubicazione' ? 'selected' : ''; ?>>UBICAZIONE</option>
                        <option value="indirrizo" <?php echo $groupBy == 'indirrizo' ? 'selected' : ''; ?>>INDIRRIZO</option>
                        <option value="comune" <?php echo $groupBy == 'comune' ? 'selected' : ''; ?>>COMUNE</option>
                        <option value="provincia" <?php echo $groupBy == 'provincia' ? 'selected' : ''; ?>>PROVINCIA</option>
                        <option value="regione" <?php echo $groupBy == 'regione' ? 'selected' : ''; ?>>REGIONE</option>
                        <option value="3" <?php echo $groupBy == '3' ? 'selected' : ''; ?>>3</option>
                        <option value="ciclo" <?php echo $groupBy == 'ciclo' ? 'selected' : ''; ?>>CICLOIN</option>
                        <option value="ciclout" <?php echo $groupBy == 'ciclout' ? 'selected' : ''; ?>>CICLOUT</option>
                        <option value="data" <?php echo $groupBy == 'data' ? 'selected' : ''; ?>>DATA</option>
                        <option value="sopra" <?php echo $groupBy == 'sopra' ? 'selected' : ''; ?>>SOPRA</option>
                        <option value="vincita" <?php echo $groupBy == 'vincita' ? 'selected' : ''; ?>>VINCITA</option>
                        <option value="manca" <?php echo $groupBy == 'manca' ? 'selected' : ''; ?>>MANCA</option>
                        <option value="ciclo" <?php echo $groupBy == 'ciclo' ? 'selected' : ''; ?>>CICLO</option>
                        <option value="percent" <?php echo $groupBy == 'percent' ? 'selected' : ''; ?>>%</option>
                        <option value="totin" <?php echo $groupBy == 'totin' ? 'selected' : ''; ?>>TOTIN</option>
                        <option value="totout" <?php echo $groupBy == 'totout' ? 'selected' : ''; ?>>TOTOUT</option>
                        <option value="fineciclo" <?php echo $groupBy == 'fineciclo' ? 'selected' : ''; ?>>FINECICLO</option>
                        <option value="ncicli" <?php echo $groupBy == 'ncicli' ? 'selected' : ''; ?>>NCICLI</option>
                        <!-- Add more options for other columns as needed -->
                    </select>
                    <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"  class="btn btn-primary">Search</button>
                </div>            
            </form>

            <h3>Data Table</h3>
            
            <div style="float: left;">Total Records: <?php echo $total_records; ?></div>
            <nav aria-label="Page navigation example">
                <ul class="pagination" style="float: right; margin:0">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $current_page - 1; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <?php if ($current_page > PAGINATION_DISPLAY_COUNT + 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=1&search=<?= $search ?>&group_by=<?= $groupBy ?>">1</a>
                        </li>
                        <?php if ($current_page > PAGINATION_DISPLAY_COUNT + 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = max(1, $current_page - PAGINATION_DISPLAY_COUNT); $i <= min($total_pages, $current_page + PAGINATION_DISPLAY_COUNT); $i++): ?>
                        <li class="page-item <?php if ($i == $current_page) echo 'active'; ?>">
                            <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages - PAGINATION_DISPLAY_COUNT): ?>
                        <?php if ($current_page < $total_pages - PAGINATION_DISPLAY_COUNT - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $total_pages; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>"><?php echo $total_pages; ?></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $current_page + 1; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><a href="?sort=codice_awp&order=<?php echo ($sortColumn == 'codice_awp' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">CODICE AWP</a></th>
                            <th><a href="?sort=denominazione&order=<?php echo ($sortColumn == 'denominazione' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">DENOMINAZIONE</a></th>
                            <th><a href="?sort=ubicazione&order=<?php echo ($sortColumn == 'ubicazione' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">UBICAZIONE</a></th>
                            <th><a href="?sort=indirrizo&order=<?php echo ($sortColumn == 'indirrizo' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">INDIRRIZO</a></th>
                            <th><a href="?sort=comune&order=<?php echo ($sortColumn == 'comune' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">COMUNE</a></th>
                            <th><a href="?sort=provincia&order=<?php echo ($sortColumn == 'provincia' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">PROVINCIA</a></th>
                            <th><a href="?sort=regione&order=<?php echo ($sortColumn == 'regione' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">REGIONE</a></th>
                            <th><a href="?sort=3&order=<?php echo ($sortColumn == '3' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">3</a></th>
                            <th><a href="?sort=ciclo&order=<?php echo ($sortColumn == 'ciclo' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">CICLOIN</a></th>
                            <th><a href="?sort=ciclout&order=<?php echo ($sortColumn == 'ciclout' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">CICLOUT</a></th>
                            <th><a href="?sort=data&order=<?php echo ($sortColumn == 'data' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">DATA</a></th>
                            <th><a href="?sort=sopra&order=<?php echo ($sortColumn == 'sopra' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">SOPRA</a></th>
                            <th><a href="?sort=vincita&order=<?php echo ($sortColumn == 'vincita' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">VINCITA</a></th>
                            <th><a href="?sort=manca&order=<?php echo ($sortColumn == 'manca' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">MANCA</a></th>
                            <th><a href="?sort=ciclo&order=<?php echo ($sortColumn == 'ciclo' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">CICLO</a></th>
                            <th><a href="?sort=percent&order=<?php echo ($sortColumn == 'percent' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">%</a></th>
                            <th><a href="?sort=totin&order=<?php echo ($sortColumn == 'totin' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">TOTIN</a></th>
                            <th><a href="?sort=totout&order=<?php echo ($sortColumn == 'totout' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">TOTOUT</a></th>
                            <th><a href="?sort=fineciclo&order=<?php echo ($sortColumn == 'fineciclo' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">FINECICLO</a></th>
                            <th><a href="?sort=ncicli&order=<?php echo ($sortColumn == 'ncicli' && $sortOrder == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?= $search ?>&group_by=<?= $groupBy ?>">NCICLI</a></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result1->num_rows > 0): ?>
                            <?php $index = $offset + 1; ?>
                            <?php while ($row = $result1->fetch_assoc()): ?>
                                
                                <?php 
                                      $I = empty($row['ciclo']) ? 0 : (intval($row['totin']) / 100) % $row['ciclo'];
                                      $O = empty($row['ciclo']) ? 0 : $row['ciclo'];
                                      $P = empty($row['percent']) ? 0 : $row['percent'];
                                      $Q = empty($row['totin']) ? 0 : $row['totin'] / 100;
                                      $T = empty($row['ciclo']) ? 0 : floor(intval($row['totin']) / intval($row['ciclo']) / 100);
                                      $S = $row['fineciclo'];
                                      $R = empty($row['totout']) ? 0 : intval($row['totout']) / 100;
                                      $J = $R - $T * ($O * intval($P) / 100 + $S);
                                      $L = $I * intval($P) / 100 - $J;
                                      $N = $O - $I;
                                      $M = $N * intval($P) / 100 - $N + $L;
                                ?>
                                <tr>
                                    <td><?php echo $index++; ?></td>
                                    <td><?php echo $row['codice_awp']; ?></td>
                                    <td class="text-nowrap"><?php echo $row['denominazione']; ?></td>
                                    <td><?php echo $row['ubicazione']; ?></td>
                                    <td class="text-nowrap"><?php echo $row['indirrizo']; ?></td>
                                    <td><?php echo $row['comune']; ?></td>
                                    <td><?php echo $row['provincia']; ?></td>
                                    <td><?php echo $row['regione']; ?></td>
                                    <td><?php echo $row['3']; ?></td>
                                    <td><?php echo $I ? $I : "";?></td>
                                    <td><?php echo $J ? $J : ""; ?></td>
                                    <td><?php echo $row['data']; ?></td>
                                    <td><?php echo $L ? $L : ""; ?></td>
                                    <td><?php echo $M ? $M : ""; ?></td>
                                    <td><?php echo $N ? $N : ""; ?></td>
                                    <td><?php echo $O ? $O : ""; ?></td>
                                    <td><?php echo $P ? $P : ""; ?></td>
                                    <td><?php echo $Q ? $Q : ""; ?></td>
                                    <td><?php echo $R ? $R : ""; ?></td>
                                    <td><?php echo $S ? $S : ""; ?></td>
                                    <td><?php echo $T ? $T : "";?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="text-center">No results found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="float: left;">Total Records: <?php echo $total_records; ?></div>
            <nav aria-label="Page navigation example">
                <ul class="pagination" style="float: right; margin-top:0">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $current_page - 1; ?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <?php if ($current_page > PAGINATION_DISPLAY_COUNT + 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=1">1</a>
                        </li>
                        <?php if ($current_page > PAGINATION_DISPLAY_COUNT + 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = max(1, $current_page - PAGINATION_DISPLAY_COUNT); $i <= min($total_pages, $current_page + PAGINATION_DISPLAY_COUNT); $i++): ?>
                        <li class="page-item <?php if ($i == $current_page) echo 'active'; ?>">
                            <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages - PAGINATION_DISPLAY_COUNT): ?>
                        <?php if ($current_page < $total_pages - PAGINATION_DISPLAY_COUNT - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?search=<?php echo urlencode($search); ?>&page=<?php echo $current_page + 1; ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <?php $conn->close(); }?>
    </div>
    <script>
        // Get the file input element
        const fileInput = document.getElementById('fileInput');

        // Add event listener to detect file selection
        fileInput.addEventListener('change', function(event) {
            const fileName = event.target.files[0].name;
            const label = document.querySelector('.custom-file-label');
            label.textContent = fileName;
        });
    </script>
    <script src="js/jquery-3.5.1.slim.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>
