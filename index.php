<?php

# Core (class)
class Notes {

    private $pdo;

    const dbFile = 'db.sqlite';

    #	Create db and table if it does not exist
    function __construct() {
	$this->pdo = new PDO('sqlite:'.self::dbFile);
	$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	$this->pdo->exec('CREATE TABLE IF NOT EXISTS notes (
	    ID      INTEGER PRIMARY KEY AUTOINCREMENT,
	    title   TEXT NOT NULL,
	    content TEXT NOT NULL,
	    created DATETIME NOT NULL
	);');
    }

    #	Get title/content for a given note ID or
    #	    all-but-content for all notes
    public function get($id = 0, $cmd = '') {
	$res = null;
	if ($id > 0) {	# Get a note
	    $stmt = $this->pdo->prepare('SELECT title,content FROM notes WHERE ID = :id');
	    $stmt->bindParam(':id', $id);
	    $stmt->execute();
	    $n = 0;
	    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		if ($n == 0) {
		    if ($cmd == 'dl') {
			header("Content-type: text/plain; charset=utf-8");
			header("Content-Disposition: attachment; filename=$row[title].txt");
			echo $row['content'];
			flush();
		    }
		    else
			$res = [ $row['title'], $row['content'] ];
		}
		$n++;
	    }
	    if ($n != 1)
		Trace("get: $n notes with id=$id ?");
	} else {	# All previous notes
	    $stmt = $this->pdo->query('SELECT ID,title,created FROM notes ORDER BY created DESC');
	    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	return $res;
    }

    #	Add (INSERT) or modify (UPDATE) a note
    public function add_mod($id, $title, $content) {
	Trace("id=$id title='$title' content='$content'");
	if ($id == 0) {	# Add
	    $datetime = date('Y-m-d H:i:s');
	    $req = 'INSERT INTO notes (title, content, created) VALUES (:title, :content, :created)';
	}
	else		# Mod
	    $req = 'UPDATE notes SET title = :title, content = :content WHERE ID = :id';

	$stmt = $this->pdo->prepare($req);

	if ($id > 0)	# Mod
	    $stmt->bindParam(':id', $id);
	else		# Add
	    $stmt->bindParam(':created', $datetime);

	$stmt->bindParam(':title', $title);
	$stmt->bindParam(':content', $content);
	$stmt->execute();
    }

    #	Remove (DELETE) a note
    public function del($id) {
	if ($id > 0) {
	    $stmt = $this->pdo->prepare('DELETE FROM notes WHERE ID = :id');
	    $stmt->bindParam(':id', $id);
	    $stmt->execute();
	}
	# else $this->pdo->query('DELETE FROM notes; VACUUM');
    }
}

#   Return text quoted for use in HTML
function htmlText($txt)
{
    return htmlspecialchars($txt, ENT_QUOTES, 'UTF-8');
}

#   Return text quoted for use in Javascript
function jsText($txt)
{
    return str_replace(["\n","\r"], ["\\n","\\r"], addslashes($txt));
}

#   Return array element or default if missing
function value($arr, $idx, $def = '')
{
    return isset($arr[$idx]) ? $arr[$idx] : $def;
}

#   Return as a string a dump of array arg.
function Dump($arr)
{
    return str_replace("\n", "\n\t", trim(print_r($arr, true)));
}

#   Write to 'trace.log' with timestamp
function Trace($txt)
{
    file_put_contents('trace.log', date('Y-m-d H:i:s')." $txt\n", FILE_APPEND);
}

#   Return password if set in .pw, false otherwise
function getPw()
{
    $pwFile = '.pw';

    if (!file_exists($pwFile))
	return false;
    if (($pw = file_get_contents($pwFile)) === false)
	return false;
    $pw = trim($pw);
    if ($pw == '')
	return false;

    return $pw;
}

#  WARNING: we need to keep the / before $base, or $_POST will be empty
$base = str_replace('.', '\.', basename($_SERVER['SCRIPT_NAME']));
$Self = preg_replace("/$base$/", '', $_SERVER['SCRIPT_NAME']);

# Init core (class)
$Notes = new Notes;

$Verb = 'Save';
$Id = 0;	# Global mode (not modifying a note)
$Title = '';
$Content = '';

$Pw = getPw();
$badPw = 'none';
if ($Pw !== false)
    session_start();

# Actions
#Trace("_POST = ".Dump($_POST).", _GET = ".Dump($_GET));
if ($Pw !== false && isset($_POST['pass']))
{
    if ($_POST['pass'] == $Pw)
    {
	$_SESSION['logedIn'] = 1;
	header("Location: $Self");
	exit();
    }
    $badPw = 'block';
}
elseif (isset($_POST['save']))
{
    $Notes->add_mod($_POST['id'], $_POST['title'], $_POST['content']);
    header("Location: $Self");
    exit();
}
elseif (isset($_GET['del'])) {
    $Notes->del($_GET['del']);
    header("Location: $Self");
    exit();
}
elseif (isset($_GET['dl'])) {
    $Notes->get($_GET['dl'], 'dl');	# Download note
    exit();
}
elseif (isset($_GET['mod'])) {
    $Id = $_GET['mod'];
    list($Title, $Content) = $Notes->get($Id);	# Get note for editing
    $Verb = 'Edit';
}
elseif (count($_POST) > 0)
    Trace("unknown member in _POST = ".Dump($_POST));
elseif (count($_GET) > 0)
    Trace("unknown member in _GET = ".Dump($_GET));

if ($Pw !== false && value($_SESSION, 'logedIn', 0) == 0)
{?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Password needed</title>
  <style>
    .box  { font-family:sans-serif; font-size:14pt; margin-top:2em; margin-left:2em; }
    .btn  { padding:.1em .5em; color:#fff; background-color:#3498db;
	    border-color:#3498db; border-radius:.2em; }
    .bad  { color:red; margin-top:1em; margin-left:4em; }
    .mr1  { margin-right:.5em; }
  </style>
</head>
<body>
  <div class="box"><!-- { -->
    <form role="form" action="<?=$Self?>" method="POST">
      <label for="pw" class="mr1">Password:</label>
      <input class="mr1" type="password" placeholder="Enter password" id="pw" name="pass" required>
      <button class="btn" type="submit" name="enter">Enter</button>
      <div id="bad" class="bad" style="display:<?=$badPw?>">Mot de passe invalide</div>
    </form>
  </div>
  <script>
    setTimeout(function() { document.getElementById('bad').style.display = 'none'; }, 1000);
  </script>
<body>
</html>
<?php
    exit();
}

$Prev = $Notes->get();			# Get all previous notes (if any)
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Simple Notes</title>
  <script src="lib/jquery-3.5.1-min.js"></script>
  <script src="lib/bootstrap-4.5.3-min.js"></script>
  <link rel="stylesheet" href="lib/bootstrap-4.5.2-min.css">
  <style>
    textarea {
	resize: vertical; /* allow only vertical stretch */
    }
  </style>
</head>

<body>
  <script type="text/javascript">
<?php if ($Id == 0) {?>
    function modifyNote(id) {
	if (document.forms.note.content.value != '')
	    alert('You must save or clear the current note first');
	else
	    window.location = '?mod=' + id;
    }

    function deleteNote(id) {
	if (confirm('Are you sure you want to delete this note?'))
	    window.location = '?del=' + id;
    }

<?php }?>
    function mod_chk(evt) {
	var t = evt.target,
	    f = document.forms.note;

	console.debug('mod_chk:', t);
	if (f.title.value != window.nTitle || f.content.value != window.nContent) {
	    if (!confirm('Changes made to the note will be discarded'))
	    {
		if (t.type == 'reset')
		    evt.preventDefault();
		return;
	    }
	}
	if (t.type != 'reset')
	    window.location.replace('<?=$Self?>');
    }

    //	Javascript page-init
    window.addEventListener('load', function() {
	var f = document.forms.note;

<?php if ($Id > 0) {?>
	f.title.value = window.nTitle = "<?=jsText($Title)?>";
	f.content.value = window.nContent = "<?=jsText($Content)?>";
<?php } else {?>
	window.nTitle = window.nContent = '';
<?php }?>
	f.clear.addEventListener('click', mod_chk);
<?php if ($Id > 0) {?>
	f.cancl.addEventListener('click', mod_chk);
<?php }?>
	f.addEventListener('submit', function(evt) {
	    var f = evt.target;

	    console.debug(evt, f.title.value, window.nTitle, f.content.value, window.nContent);
	    if (f.title.value == window.nTitle && f.content.value == window.nContent) {
		evt.preventDefault();
		alert('The note was not modified');
		window.location.replace('<?=$Self?>');
	    }
	});
    });
  </script>
  <div class="container"><!-- { -->
    <div class="page-header">
      <h2> <?=$Verb?> a note </h2>
    </div>

    <form role="form" name="note" action="<?=$Self?>" method="POST">
      <div class="form-group">
	<input class="form-control" type="text" placeholder="Title" name="title" required>
      </div>
      <div class="form-group">
	<textarea class="form-control" rows="5" placeholder="What do you want to save?" name="content" autofocus required></textarea>
      </div>
      <div class="btn-group float-right">
<?php if ($Id > 0) {?>
	<button class="btn btn-info" type="button" name="cancl">Cancel</button>
<?php }?>
	<button class="btn btn-danger" type="reset" name="clear">Clear</button>
	<button class="btn btn-success" type="submit" name="save">Save</button>
      </div>
      <input type="hidden" name="id" value="<?=$Id?>">
    </form>
  </div><!-- } container (input) -->

<?php if ($Id == 0 && count($Prev) > 0) {?>
  <div class="container mt-5" id="notes"><!-- { -->
    <div class="page-header">
      <h2>Previously saved</h2>
    </div>

    <div class="table-responsive"><!-- { -->
      <table class="table table-hover">
	<thead>
	  <tr>
	    <th>Name</th>
	    <th class="text-right">Date</th>
	    <th class="text-right">Time</th>
	    <th class="text-right">Actions<br></th>
	  </tr>
	</thead>
	<tbody>
	  <tr>
<?php foreach ($Prev as $row) {?>
	    <td> <?=htmlText(substr($row['title'], 0, 15))?> </td>
	    <td class="text-right"><?=date('d/m/Y', strtotime($row['created'])) ?></td>
	    <td class="text-right"><?=date('H:i', strtotime($row['created'])) ?></td>
	    <td class="text-right">
	      <div class="btn-group">
		<a class="btn btn-secondary btn-sm" title="Edit this note" onclick="modifyNote(<?=$row['ID']?>)">Edit</a>
		<a class="btn btn-danger btn-sm" title="Delete this note" onclick="deleteNote(<?=$row['ID']?>)">Delete</a>
		<a class="btn btn-info btn-sm" title="Download this note" href="?dl=<?=$row['ID']?>" target="_blank">Get</a>
	      </div>
	    </td>
	  </tr>
<?php	}?>
	</tbody>
      </table>
    </div><!-- } table-responsive -->
  </div><!-- } container -->
<?php }?>
</body>
</html>
