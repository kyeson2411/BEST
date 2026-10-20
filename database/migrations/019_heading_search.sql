-- Phase 3, item 18: a passage is also found by its heading ("Common mistakes"), not only by its text.
ALTER TABLE material_chunks DROP INDEX ft_chunks_content, ADD FULLTEXT KEY ft_chunks_text (section_title, content);
