<?php
session_start();

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "admin123";
$database = "csvimport";

$conn = new mysqli($servername, $username, $password, $database);
$result1 = [];
$row = [];
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
    } elseif ($fileType === 'xlsm') {
        require 'vendor/autoload.php';
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
if(isset($_POST['login'])){
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result = $conn->query($sql);

    if($result->num_rows > 0){
        $_SESSION['username'] = $username;
        header("Location: index.php");
    } else {
        echo "Invalid username or password";
    }
}

// User registration handling
if(isset($_POST['register'])){
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "INSERT INTO users (username, password) VALUES ('$username', '$password')";
    if ($conn->query($sql) === TRUE) {
        echo "Registration successful";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

if(isset($_GET['search'])){
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $sql = "SELECT * FROM indirrizzi WHERE gestore LIKE '%$search%' OR mac_address LIKE '%$search%' OR num_awp LIKE '%$search%' OR identificativo LIKE '%$search%' OR modello LIKE '%$search%' OR codiceUbicazione LIKE '%$search%' OR ubicazione LIKE '%$search%' OR indirizzo LIKE '%$search%' OR comune LIKE '%$search%' OR provincia LIKE '%$search%' OR regione LIKE '%$search%' OR modelloAAMS LIKE '%$search%'";
    $result1 = $conn->query($sql);
    $row = $result1->fetch_assoc();
    // $total_records = $row['total'];
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
                <div class="col-md-6">
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
                </div>
            </div>
        <?php } else { ?>
            <h3>Upload CSV/XLSM/ODS File</h2>
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


            <h3>Search Data</h2>
            <form method="get" action="" class="mb-4">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </div>
            </form>

            
            <h3>Data Table</h2>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Gestore</th>
                            <th>Mac Address</th>
                            <th>Num Awp</th>
                            <th>Identificativo</th>
                            <th>Modello</th>
                            <th>Codice Ubicazione</th>
                            <th>Ubicazione</th>
                            <th>Indirizzo</th>
                            <th>Comune</th>
                            <th>Provincia</th>
                            <th>Regione</th>
                            <th>Modello AAMS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($row)): ?>
                            <?php $index = 1; ?>
                            <?php while ($row): ?>
                                <tr>
                                    <td><?php echo $index++; ?></td>
                                    <td><?php echo $row['gestore']; ?></td>
                                    <td><?php echo $row['mac_address']; ?></td>
                                    <td><?php echo $row['num_awp']; ?></td>
                                    <td><?php echo $row['identificativo']; ?></td>
                                    <td><?php echo $row['modello']; ?></td>
                                    <td><?php echo $row['codiceUbicazione']; ?></td>
                                    <td><?php echo $row['ubicazione']; ?></td>
                                    <td><?php echo $row['indirizzo']; ?></td>
                                    <td><?php echo $row['comune']; ?></td>
                                    <td><?php echo $row['provincia']; ?></td>
                                    <td><?php echo $row['regione']; ?></td>
                                    <td><?php echo $row['modelloAAMS']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">No results found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
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
