//CyberWeb Main JavaScript
//Handles interactive features

//Profile Menu Toggle
document.addEventListener('DOMContentLoaded', function() {
    const profileMenuBtn = document.getElementById('profileMenuBtn');
    const profileDropdown = document.getElementById('profileDropdown');

    if (profileMenuBtn && profileDropdown) {
        profileMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        //Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileMenuBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        });
    }
});

//Toggle Post Menu
function togglePostMenu(postId) {
    const menu = document.getElementById('postMenu' + postId);
    if (menu) {
        //Close all other menus
        document.querySelectorAll('.post-dropdown').forEach(m => {
            if (m !== menu) m.classList.remove('show');
        });
        menu.classList.toggle('show');
    }
}

//Close post menus when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.post-menu')) {
        document.querySelectorAll('.post-dropdown').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

//Toggle Profile Action Menu
function toggleProfileMenu() {
    const menu = document.getElementById('profileActionMenu');
    if (menu) {
        menu.classList.toggle('show');
    }
}

//Like/Unlike Post
async function toggleLike(postId) {
    try {
        const formData = new FormData();
        formData.append('action', 'like');
        formData.append('post_id', postId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            //Update UI
            const postCard = document.querySelector(`[data-post-id="${postId}"]`);
            if (postCard) {
                const likeBtn = postCard.querySelector('.like-btn');
                const likeIcon = likeBtn.querySelector('i');
                const likeCount = likeBtn.querySelector('.like-count');

                if (data.liked) {
                    likeBtn.classList.add('liked');
                    likeIcon.classList.remove('far');
                    likeIcon.classList.add('fas');
                } else {
                    likeBtn.classList.remove('liked');
                    likeIcon.classList.remove('fas');
                    likeIcon.classList.add('far');
                }

                likeCount.textContent = data.count;
            }
        } else {
            alert(data.error || 'Failed to like post');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}

//Format date: "X hrs ago" if within 24 hours, otherwise "dd/mm/yyyy"
function formatPostDate(timestamp) {
    const now = new Date();
    const postDate = new Date(timestamp);
    const diffMs = now - postDate;
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));

    //Within 24 hours: show hours ago
    if (diffHours < 24) {
        if (diffHours === 0) {
            return "less than 1hr ago";
        }
        return diffHours + "hrs ago";
    }

    //Over 24 hours: show dd/mm/yyyy
    const day = String(postDate.getDate()).padStart(2, '0');
    const month = String(postDate.getMonth() + 1).padStart(2, '0');
    const year = postDate.getFullYear();
    return `${day}/${month}/${year}`;
}

//Show Comments Modal
async function showComments(postId) {
    try {
        //Fetch comments for the post
        const response = await fetch(`api.php?action=get_comments&post_id=${postId}`);
        const data = await response.json();

        if (data.success) {
            //Create or update comments modal
            let modal = document.getElementById('commentsModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'commentsModal';
                modal.className = 'modal';
                document.body.appendChild(modal);
            }

            //Build comments HTML
            let commentsHtml = '';
            if (data.comments && data.comments.length > 0) {
                data.comments.forEach(comment => {
                    const profilePic = comment.profile_picture || 'default-avatar.svg';
                    const formattedDate = formatPostDate(comment.created_at);

                    commentsHtml += `
                        <div class="comment-item">
                            <img src="uploads/profiles/${profilePic}"
                                 alt="${comment.username}"
                                 class="comment-profile-pic"
                                 onerror="this.src='uploads/profiles/default-avatar.svg';">
                            <div class="comment-content">
                                <div class="comment-header">
                                    <span class="comment-username">${comment.username}</span>
                                    <span class="comment-date">${formattedDate}</span>
                                </div>
                                <p>${comment.content}</p>
                            </div>
                        </div>
                    `;
                });
            } else {
                commentsHtml = '<p class="no-comments">No comments yet. Be the first to comment!</p>';
            }

            //Create modal content
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Comments</h3>
                        <button class="btn-close" onclick="closeCommentsModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="comments-list">
                            ${commentsHtml}
                        </div>
                        <form class="comment-form" onsubmit="submitComment(event, ${postId})">
                            <input type="hidden" name="post_id" value="${postId}">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                            <div class="form-group">
                                <textarea name="content" placeholder="Write a comment..." required rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Post Comment</button>
                        </form>
                    </div>
                </div>
            `;

            modal.classList.add('show');
        } else {
            alert(data.error || 'Failed to load comments');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while loading comments.');
    }
}

//Close Comments Modal
function closeCommentsModal() {
    const modal = document.getElementById('commentsModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

//Submit Comment
async function submitComment(event, postId) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'comment');

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            //Reload comments
            await showComments(postId);

            //Update comment count in the post card
            const postCard = document.querySelector(`[data-post-id="${postId}"]`);
            if (postCard) {
                const commentCountSpan = postCard.querySelector('.comment-count');
                if (commentCountSpan) {
                    const currentCount = parseInt(commentCountSpan.textContent) || 0;
                    commentCountSpan.textContent = currentCount + 1;
                }
            }
        } else {
            alert(data.error || 'Failed to post comment');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}

//Follow/Unfollow User
async function toggleFollow(userId) {
    try {
        const formData = new FormData();
        formData.append('action', 'follow');
        formData.append('user_id', userId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            const followBtn = document.getElementById('followBtn');
            if (followBtn) {
                if (data.following) {
                    followBtn.textContent = 'Unfollow';
                    followBtn.classList.remove('btn-primary');
                    followBtn.classList.add('btn-secondary');
                } else {
                    followBtn.textContent = 'Follow';
                    followBtn.classList.remove('btn-secondary');
                    followBtn.classList.add('btn-primary');
                }
            }

            //Reload page to update stats
            setTimeout(() => location.reload(), 500);
        } else {
            alert(data.error || 'Failed to follow/unfollow user');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}

//Block User
async function blockUser(userId) {
    if (!confirm('Are you sure you want to block this user? This will remove all follow relationships.')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'block');
        formData.append('user_id', userId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('User blocked successfully');
            location.reload();
        } else {
            alert(data.error || 'Failed to block user');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}

//Unblock User
async function unblockUser(userId) {
    if (!confirm('Are you sure you want to unblock this user?')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'unblock');
        formData.append('user_id', userId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('User unblocked successfully');
            location.reload();
        } else {
            alert(data.error || 'Failed to unblock user');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}

//Report Post
function reportPost(postId) {
    const modal = document.getElementById('reportModal');
    const reportPostIdInput = document.getElementById('reportPostId');
    const reportUserIdInput = document.getElementById('reportUserId');
    const reportTypeInput = document.getElementById('reportType');
    const modalTitle = document.querySelector('#reportModal .modal-header h3');
    
    if (modal && reportPostIdInput) {
        // Clear user ID, set post ID
        reportUserIdInput.value = '';
        reportPostIdInput.value = postId;
        reportTypeInput.value = 'post';
        modalTitle.textContent = 'Report Post';
        
        // Clear the form
        document.getElementById('reportReason').value = '';
        
        modal.classList.add('show');
    }
}

//Report User
function reportUser(userId) {
    const modal = document.getElementById('reportModal');
    const reportUserIdInput = document.getElementById('reportUserId');
    const reportPostIdInput = document.getElementById('reportPostId');
    const reportTypeInput = document.getElementById('reportType');
    const modalTitle = document.querySelector('#reportModal .modal-header h3');
    
    if (modal && reportUserIdInput) {
        // Clear post ID, set user ID
        reportPostIdInput.value = '';
        reportUserIdInput.value = userId;
        reportTypeInput.value = 'user';
        modalTitle.textContent = 'Report User';
        
        // Clear the form
        document.getElementById('reportReason').value = '';
        
        modal.classList.add('show');
    }
}

//Submit Report (updated to handle both types)
async function submitReport(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'report');

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert(data.message || 'Report submitted successfully');
            closeReportModal();
            form.reset();
        } else {
            alert(data.error || 'Failed to submit report');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}

//Close Report Modal
function closeReportModal() {
    const modal = document.getElementById('reportModal');
    if (modal) {
        modal.classList.remove('show');
        // Reset all hidden inputs
        document.getElementById('reportPostId').value = '';
        document.getElementById('reportUserId').value = '';
        document.getElementById('reportType').value = '';
    }
}

//Delete Post
async function deletePost(postId) {
    if (!confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'delete_post');
        formData.append('post_id', postId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            //Remove post card from DOM
            const postCard = document.querySelector(`[data-post-id="${postId}"]`);
            if (postCard) {
                postCard.style.opacity = '0';
                postCard.style.transform = 'scale(0.9)';
                postCard.style.transition = 'all 0.3s';
                setTimeout(() => postCard.remove(), 300);
            }
        } else {
            alert(data.error || 'Failed to delete post');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}

//Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});

//Close modal when clicking outside
window.onclick = function(event) {
    const reportModal = document.getElementById('reportModal');
    const commentsModal = document.getElementById('commentsModal');

    if (event.target === reportModal) {
        closeReportModal();
    }

    if (event.target === commentsModal) {
        closeCommentsModal();
    }
};

function toggleProfileMenu() {
  const menu = document.getElementById("profileActionMenu");
  menu.classList.toggle("show");
}

// Close the dropdown when clicking outside of it
document.addEventListener("click", function(event) {
  const menu = document.getElementById("profileActionMenu");
  const button = document.querySelector(".profile-menu-btn button");

  // If the click isn't on the button or inside the menu, close it
  if (!menu.contains(event.target) && !button.contains(event.target)) {
    menu.classList.remove("show");
  }
});