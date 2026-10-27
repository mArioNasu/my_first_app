<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['username'];

// DELETE POST
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $res = $conn->query("SELECT image_path FROM messages WHERE id = $id");
    if ($row = $res->fetch_assoc()) {
        if (!empty($row['image_path']) && file_exists($row['image_path'])) {
            unlink($row['image_path']);
        }
    }
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php");
    exit();
}

// READ ALL POSTS
$result = $conn->query("SELECT * FROM messages ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instant Feed (AJAX)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">

    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-4xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold text-slate-800">⚡ Instant Community Feed</h1>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-slate-600">Logged in as <strong class="text-slate-900"><?php echo htmlspecialchars($current_user); ?></strong></span>
                <a href="logout.php" class="bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-sm font-medium px-3 py-1.5 rounded-lg transition">Logout</a>
            </div>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto px-4 py-8 space-y-8">

        <!-- Form Section -->
        <div class="bg-white p-6 rounded-xl shadow-sm border">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Share something (No Reload!)</h2>
            <form id="postForm" class="space-y-4">
                <textarea id="messageInput" name="message" placeholder="What's on your mind?" rows="3" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Attach Image:</label>
                    <input type="file" id="imageInput" name="image" accept="image/*" class="text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>

                <button type="submit" id="submitBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-2 rounded-lg transition">Post Instantly</button>
            </form>
        </div>

        <!-- Feed List -->
        <div class="space-y-4">
            <h2 class="text-xl font-bold text-slate-800">Recent Posts</h2>
            <div id="feedContainer" class="space-y-4">
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <div class="bg-white p-6 rounded-xl shadow-sm border space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-slate-900"><?php echo htmlspecialchars($row['name']); ?></span>
                                <span class="text-xs text-slate-400"><?php echo date('M d, Y • h:i A', strtotime($row['created_at'])); ?></span>
                            </div>
                            
                            <p class="text-slate-700 leading-relaxed"><?php echo htmlspecialchars($row['message']); ?></p>

                            <?php if (!empty($row['image_path'])): ?>
                                <div class="mt-3">
                                    <img src="<?php echo htmlspecialchars($row['image_path']); ?>" alt="User upload" class="rounded-lg max-h-80 w-full object-cover border">
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($row['name'] === $current_user): ?>
                                <div class="flex space-x-4 pt-2 border-t text-sm font-medium">
                                    <a href="index.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete post?');" class="text-red-500 hover:underline">Delete</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- AJAX Script -->
    <script>
        document.getElementById('postForm').addEventListener('submit', async function(e) {
            e.preventDefault(); // Stop normal form submission (page reload)

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerText = 'Posting...';

            const formData = new FormData(this);

            try {
                const response = await fetch('api_create_post.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Reset input fields
                    document.getElementById('messageInput').value = '';
                    document.getElementById('imageInput').value = '';

                    // Build new post HTML card dynamically
                    let imageHTML = data.post.image_path 
                        ? `<div class="mt-3"><img src="${data.post.image_path}" class="rounded-lg max-h-80 w-full object-cover border"></div>` 
                        : '';

                    const newPostCard = document.createElement('div');
                    newPostCard.className = 'bg-white p-6 rounded-xl shadow-sm border space-y-3';
                    newPostCard.innerHTML = `
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-slate-900">${data.post.name}</span>
                            <span class="text-xs text-slate-400">${data.post.created_at}</span>
                        </div>
                        <p class="text-slate-700 leading-relaxed">${data.post.message}</p>
                        ${imageHTML}
                        <div class="flex space-x-4 pt-2 border-t text-sm font-medium">
                            <a href="index.php?delete=${data.post.id}" onclick="return confirm('Delete post?');" class="text-red-500 hover:underline">Delete</a>
                        </div>
                    `;

                    // Insert top of feed container
                    const feed = document.getElementById('feedContainer');
                    feed.prepend(newPostCard);
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (err) {
                alert('Request failed');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Post Instantly';
            }
        });
    </script>

</body>
</html>