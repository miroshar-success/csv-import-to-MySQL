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
                    "start_row" => 3,
                    "start_col" => "A",
                    "columns" => [
                        0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17
                    ]
                ]
            ],
            // [
            //     "db" => [
            //         "table" => 'lotto',
            //         "columns" => [
            //             "codice_awp"
            //         ],
            //     ],
            //     "sheet" => [
            //         "index" => 3,
            //         "start_row" => 216,
            //         "start_col" => "A",
            //         "columns" => [
            //             0
            //         ]
            //     ]
            // ],
            [
                "db" => [
                    "table" => 'provincia',
                    "columns" => [
                        "colA", "colB"
                    ],
                ],
                "sheet" => [
                    "index" => 4,
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
            
            $sheet = $spreadsheet->setActiveSheetIndex($e["sheet"]["index"]);

            $maxCol = $sheet->getHighestColumn();
            $maxRow = $sheet->getHighestRow();

            $data = $sheet->rangeToArray($e['sheet']['start_col'] . $e['sheet']['start_row'] . ":{$maxCol}$maxRow");

            $q_rows = [];
            for ($i = 0; $i < count($data); $i++) {
                $row = $data[$i];

                $arr_data = [];
                foreach ($e['sheet']["columns"] as $col) {
                    $val = str_replace("\"", "", $row[$col]);
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
        
    }
}

// User login handling
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['username'] = $username;
            header("Location: index.php");
        } else {
            echo "Invalid username or password";
        }
    } else {
        echo "Invalid username or password";
    }
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

// Check if search is performed
if ($search !== '') {
    // Perform search query
    $sql = "SELECT * FROM lotto WHERE codice_awp LIKE '%$search%' OR denominazione LIKE '%$search%' OR ubicazione LIKE '%$search%' OR indirrizo LIKE '%$search%' OR comune LIKE '%$search%' OR provincia LIKE '%$search%' OR regione LIKE '%$search%' OR 3 LIKE '%$search%' OR cicloin LIKE '%$search%' OR ciclout LIKE '%$search%' OR data LIKE '%$search%' OR sopra LIKE '%$search%' OR vincita LIKE '%$search%' OR manca LIKE '%$search%' OR ciclo LIKE '%$search%' OR % LIKE '%$search%' OR totin LIKE '%$search%' OR totout LIKE '%$search%' OR fineciclo LIKE '%$search%' OR ncicli LIKE '%$search%'";
    $result = $conn->query($sql);
    $total_records = $result->num_rows;
    
    $records_per_page = 10;
    $total_pages = ceil($total_records / $records_per_page);
    
    if (isset($_GET['page']) && is_numeric($_GET['page'])) {
        $current_page = (int) $_GET['page'];
    }

    $offset = ($current_page - 1) * $records_per_page;

    $sql = "SELECT * FROM lotto WHERE codice_awp LIKE '%$search%' OR denominazione LIKE '%$search%' OR ubicazione LIKE '%$search%' OR indirrizo LIKE '%$search%' OR comune LIKE '%$search%' OR provincia LIKE '%$search%' OR regione LIKE '%$search%' OR 3 LIKE '%$search%' OR cicloin LIKE '%$search%' OR ciclout LIKE '%$search%' OR data LIKE '%$search%' OR sopra LIKE '%$search%' OR vincita LIKE '%$search%' OR manca LIKE '%$search%' OR ciclo LIKE '%$search%' OR % LIKE '%$search%' OR totin LIKE '%$search%' OR totout LIKE '%$search%' OR fineciclo LIKE '%$search%' OR ncicli LIKE '%$search%' LIMIT $offset, $records_per_page";
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

    $sql = "SELECT * FROM lotto LIMIT $offset, $records_per_page";
    $result1 = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Import Data</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
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
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>CODICE AWP</th>
                            <th>DENOMINAZIONE</th>
                            <th>UBICAZIONE</th>
                            <th>INDIRRIZO</th>
                            <th>COMUNE</th>
                            <th>PROVINCIA</th>
                            <th>REGIONE</th>
                            <th>3</th>
                            <th>CICLOIN</th>
                            <th>CICLOUT</th>
                            <th>DATA</th>
                            <th>SOPRA</th>
                            <th>VINCITA</th>
                            <th>MANCA</th>
                            <th>CICLO</th>
                            <th>%</th>
                            <th>TOTIN</th>
                            <th>TOTOUT</th>
                            <th>FINECICLO</th>
                            <th>NCICLI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result1->num_rows > 0): ?>
                            <?php $index = $offset + 1; ?>
                            <?php while ($row = $result1->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $index++; ?></td>
                                    <td><?php echo $row['codice_awp']; ?></td>
                                    <td><?php echo $row['denominazione']; ?></td>
                                    <td><?php echo $row['ubicazione']; ?></td>
                                    <td><?php echo $row['indirrizo']; ?></td>
                                    <td><?php echo $row['comune']; ?></td>
                                    <td><?php echo $row['provincia']; ?></td>
                                    <td><?php echo $row['regione']; ?></td>
                                    <td><?php echo $row['3']; ?></td>
                                    <td><?php echo $row['cicloin']; ?></td>
                                    <td><?php echo $row['ciclout']; ?></td>
                                    <td><?php echo $row['data']; ?></td>
                                    <td><?php echo $row['sopra']; ?></td>
                                    <td><?php echo $row['vincita']; ?></td>
                                    <td><?php echo $row['manca']; ?></td>
                                    <td><?php echo $row['ciclo']; ?></td>
                                    <td><?php echo $row['%']; ?></td>
                                    <td><?php echo $row['totin']; ?></td>
                                    <td><?php echo $row['totout']; ?></td>
                                    <td><?php echo $row['fineciclo']; ?></td>
                                    <td><?php echo $row['ncicli']; ?></td>

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
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
