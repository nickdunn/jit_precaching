<?php

	Class extension_jit_precaching extends Extension{
	
		public function about(){
			return array(
				'name' => 'JIT Image Caching',
				'version' => '0.0.1',
				'release-date' => '2011-07-19',
				'author' => array(
					'name' => 'Nick Dunn'
				)
			);
		}
		
		public function install(){
			
			if(file_exists(MANIFEST . '/jit-precaching.php')) return;
			
			$string = "<?php\n";
			$string .= "\n\t\$cached_recipes = array(";			
			$string .= "\r\n\r\n\t\t/*";
			$string .= "\r\n\t\tarray(";
			$string .= "\r\n\t\t\t'section' => 'section-handle',";
			$string .= "\r\n\t\t\t'field' => 'field-handle',";
			$string .= "\r\n\t\t\t//'recipes' => array('*'),";
			$string .= "\r\n\t\t\t'recipes' => array('gallery', 'profile-small', 'profile-large'),";
			$string .= "\r\n\t\t),";
			$string .= "\r\n\t\t*/";			
			$string .= "\r\n\t);\n\n";

			return General::writeFile(MANIFEST . '/jit-precaching.php', $string, Symphony::Configuration()->get('write_mode', 'file'));
		}

		public function uninstall(){
			if(file_exists(MANIFEST . '/jit-precaching.php')) unlink(MANIFEST . '/jit-recipes.php');
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/publish/new/',
					'delegate'	=> 'EntryPostCreate',
					'callback'	=> 'entrySaved'
				),				
				array(
					'page'		=> '/publish/edit/',
					'delegate'	=> 'EntryPostEdit',
					'callback'	=> 'entrySaved'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'EventPostSaveFilter',
					'callback'	=> 'entrySaved'
				),
			);
		}
		
		public function entrySaved($context) {
			
			require_once(MANIFEST . '/jit-recipes.php');
			require_once(MANIFEST . '/jit-precaching.php');
			
			require_once(TOOLKIT . '/class.fieldmanager.php');
			$fm = new FieldManager(Symphony::Engine());
			
			$section = $context['section'];
			if(!$section) {
				require_once(TOOLKIT . '/class.sectionmanager.php');
				$sm = new SectionManager(Symphony::Engine());
				$section = $sm->fetch($context['entry']->get('section_id'));
			}
			
			// iterate over each field in this entry
			foreach($context['entry']->getData() as $field_id => $data) {
				
				// get the field meta data
				$field = $fm->fetch($field_id);
				
				// iterate over the field => recipe mapping
				foreach($cached_recipes as $cached_recipe) {
					
					// check a mapping exists for this section/field combination
					if($section->get('handle') != $cached_recipe['section']) continue;
					if($field->get('element_name') != $cached_recipe['field']) continue;
					
					// iterate over the recipes mapped for this section/field combination
					foreach($cached_recipe['recipes'] as $cached_recipe_name) {
						
						// get the file name, includes path relative to workspace
						$file = $data['file'];
						if(!isset($file) || is_null($file)) continue;
						
						// trim the filename from path
						$uploaded_file_path = explode('/', $file);
						array_pop($uploaded_file_path);
						// image path relative to workspace
						if(is_array($uploaded_file_path)) $uploaded_file_path = implode('/', $uploaded_file_path);

						// iterate over all JIT recipes
						foreach($recipes as $recipe) {
							
							// only process if the recipe has a URL Parameter (name)
							if(is_null($recipe['url-parameter'])) continue;
							// if not using wildcard, only process specified recipe names
							if($cached_recipe_name != '*' && $cached_recipe_name != $recipe['url-parameter']) continue;
							
							// process the image using the usual JIT URL and get the result
							$image_data = file_get_contents(URL . '/image/' . $recipe['url-parameter'] . $file);
							
							// create a directory structure that matches the JIT URL structure
							General::realiseDirectory(WORKSPACE . '/image-cache/' . $recipe['url-parameter'] . $uploaded_file_path );
							
							// save the image to disk
							file_put_contents(WORKSPACE . '/image-cache/' . $recipe['url-parameter'] . $file, $image_data);
							
						}
						
					}
				}
				
			}
		
		}
		
	}