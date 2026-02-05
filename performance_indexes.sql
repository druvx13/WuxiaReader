-- Performance Optimization: Add Missing Database Indexes
-- This migration adds indexes to improve query performance

-- Add composite index on chapters for prev/next navigation queries
-- This optimizes findPrevious() and findNext() methods
ALTER TABLE chapters ADD INDEX idx_novel_order (novel_id, order_index);

-- Add composite index on comments for better filtering
-- This helps when fetching comments by novel and sorting by date
ALTER TABLE comments ADD INDEX idx_novel_created (novel_id, created_at);

-- Add composite index on comments for chapter-based queries
ALTER TABLE comments ADD INDEX idx_chapter_created (chapter_id, created_at);

-- Add index on likes.user_id for toggle operations
-- This improves performance when checking user's likes
ALTER TABLE likes ADD INDEX idx_user_id (user_id);
