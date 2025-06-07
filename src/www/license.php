<?php
require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("license_utils.inc");

session_start();

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $entered_key = $_POST["license_key"] ?? "";
    if (validate_license($entered_key)) {
        $_SESSION["license_key"] = $entered_key;
        header("Location: /index.php"); 
        exit;
    } else {
        $error = "Incorect License";
    }
}

include("head.inc");
?>
<body>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row" style="width: 300px; margin: 0 auto;">
        <h1>License Verification</h1> 
        <form method="post">
          <div class="form-group">
	    <label for="license_key">Enter licence code:</label>
            <input type="text" name="license_key" id="license_key" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary">Submit</button>
        <?php if ($error): ?>
          <div class="text-danger" style="margin-top: 8px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?> 
        </form>
        </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
</body>
</html>

