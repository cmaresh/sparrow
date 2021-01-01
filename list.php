<?php session_start(); ?>
<?php
$servername = "127.0.0.1:3306";
$username = "root";
$password = "";
$database = "sparrow";


$curruser = 1;
setcookie('id', 1);
// Create connection
$conn = new mysqli($servername, $username, $password, $database);

$listId = $_GET['id'];

//Retrieve list name
$sql = "SELECT name, owner, locked FROM lists WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $listId);

$list = [];

if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $list[] = $row;
    }
}

$access = true;
$exists = false;

if (count($list) > 0) {
    $exists = true;
    if ($list[0]['locked'] && (
        !isset($_SESSION['user']) ||
            ( isset($_SESSION['user']) && $list[0]['owner'] !== $_SESSION['user']) )
        ) 
    {
        $access = false;
    }
}

//Retrieve list items
if ($access) {
    $sql = "SELECT id, content FROM items WHERE parent = ".$listId;
    $result = $conn->query($sql);
    $lists = [];
    while($row = $result->fetch_assoc()) {
        $lists[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html>
<?php include './head.php'; ?>

<body>
<?php include './header.php'; ?>

<div class="backdrop birds"></div>
<section id="lists">
    <div class="container padded"><div class="row"><div class="col-12">
        <?php if (!$exists): ?>
        <div id="no-access">
            <div>a list with this ID does not exist</div>
            <a href="/sparrow/">HOME ></a>
        </div>
        <?php endif; ?>
        <?php if ($exists && $access): ?>
        <h2><?php echo $list[0]['name']; ?></h2>
        <div class="list-settings">
            <h3 id="lock" class="<?php echo $list[0]['locked'] ? 'unlocked' : 'locked'; ?>">
            <div class="unlocked-content"><svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-lock-fill" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 9a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-7a2 2 0 0 1-2-2V9z"/><path fill-rule="evenodd" d="M4.5 4a3.5 3.5 0 1 1 7 0v3h-1V4a2.5 2.5 0 0 0-5 0v3h-1V4z"/></svg>&nbsp;
            Locked</div>
            <div class="locked-content">
            Unlocked</div>
            </h3>
            <h3 class="delete">Delete?</h3>
        </div>
        <div class="list-items">
        <?php foreach($lists as $l) { echo '<h5 class="list-name" data-id="'.$l['id'].'">'.$l['content'].'</h5>'; } ?>
        </div>
        <div class="list-options">
            <div class="list-option add"><h6>+</h6></div>
            <div class="list-option remove"><h6>-</h6></div>
        </div>
        <?php endif; ?>
        <?php if ($exists && !$access): ?>
            <div id="no-access">
                <div>this list has not yet been unlocked by the owner</div>
                <a href="/">back to home</a>
            </div>
        <?php endif; ?>
    </div></div></div>
</section>

</body>
<script>
    creatingNew = false;
    $(document).ready(function() {
        addNew = $('.add');
        remove = $('.remove');
    
        addNew.click(function() {
            if (!creatingNew) {
                creatingNew = true;
                $('.add').html(`
                    <input id="new-list-name" type='text' placeholder="Enter a list item">
                `);
            }
        });

        remove.click(function() {
            selected = $('.selected');
            removeIds = [];
            selected.each(function() {
                removeIds.push($(this).attr('data-id'));
            });
            $.post('/sparrow/api/removeitems.php', { listId: <?php echo $listId; ?>, ids: removeIds }, function(data) {
                data = JSON.parse(data);
                $('.selected').remove();
                remove.hide();
            });
        });

        $('.delete').click(function() {
            $.post('/sparrow/api/deletelist.php', { user: "<?php echo $_SESSION['user']; ?>", id: <?php echo $listId; ?> }, function(data) {
                window.location.href = '/sparrow/lists.php';
            });
        });

        $(document).keypress(function(event){
            var keycode = (event.keyCode ? event.keyCode : event.which);
            var content = $('#new-list-name').val();
            if(keycode == '13' && creatingNew){
                $.post('/sparrow/api/newlistitem.php', { user: "<?php echo $_SESSION['user']?>", content: content, parent: <?php echo $listId ?> }, function(data) {
                    data = JSON.parse(data);
                    var newItemHtml = '<h5 class="list-name" data-id="' + data.id +'">' + data.content + '</h5>'
                    $(newItemHtml).appendTo('.list-items').click(function() {
                        $(this).toggleClass('selected');
                        $('.selected').length > 0 ? $('.remove').show() : $('.remove').hide();
                    });
                    $('.add').html(`
                    <h6>+</h6>
                    `);
                });
                creatingNew = false;
            }
        });
        

        $('.list-name').click(function() {
            $(this).toggleClass('selected');
            $('.selected').length > 0 ? $('.remove').show() : $('.remove').hide();
        });

        $('#lock').click(function() {
            $.post('/sparrow/api/togglelock.php', { id: <?php echo $listId; ?> });
            $(this).toggleClass('locked');
        })
    });
</script>
</html>