<?php

class CSSLisible {

    public $buffer = '';
    public $listing_proprietes = array();
    public $listing_indentations = array(
        array(' ', '1 espace'),
        array('  ', '2 espaces'),
        array('   ', '3 espaces'),
        array('    ', '4 espaces'),
        array("\t", '1 tab'),
        array("\t\t", '2 tabs'),
        array("", 'Aucune'),
    );
    public $listing_separateurs = array(
        ':',
        ' :',
        ': ',
        ' : ',
    );
    public $listing_distances = array(
        'Aucune',
        'Une',
        'Deux'
    );
    public $listing_hex_colors_formats = array(
        'Inchangé',
        'Minuscules',
        'Majuscules'
    );
    private $options = array(
        'separateur' => 0,
        'indentation' => 4,
        'distance_selecteurs' => 1,
        'hex_colors_format' => 0,
        'selecteurs_multiples_separes' => true,
        'supprimer_selecteurs_vides' => false,
        'selecteur_par_ligne' => false,
        'tout_compresse' => false,
    );
    private $strings_tofix = array(
        'url_data_etc' => array(
            'regex' => '#url\((.*)\)#',
            'list' => array()
        ),
        'ms_filter' => array(
            'regex' => '#progid(.*);#',
            'list' => array()
        ),
    );

    function __construct($listing_proprietes = array()) {

        $this->listing_proprietes = $listing_proprietes;
        $this->init_session();

        if (isset($_POST['clean_css'])) {
            $this->buffer = get_magic_quotes_gpc() ? stripslashes($_POST['clean_css']) : $_POST['clean_css'];
            $this->get_options_from_post();

            $this->buffer = $this->mise_ecart_propriete($this->buffer);
            $this->buffer = $this->clean_css($this->buffer);
            $this->buffer = $this->sort_css($this->buffer);
            $this->buffer = $this->reindent_media_queries($this->buffer);
            $this->buffer = $this->suppression_mise_ecart_propriete($this->buffer);

			if($this->get_option('tout_compresse')){
				$this->buffer = $this->compress_css($this->buffer,1);
			}
			
			$this->buffer = $this->add_header($this->buffer);
			
        } else {
            $this->get_options_from_session();
        }
    }

    private function init_session() {
        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['CSSLisible'])) {
            $_SESSION['CSSLisible'] = array('options' => array());
        }
    }

    // On vérifie la présence de réglages dans la session
    private function get_options_from_session() {
        foreach ($this->options as $option => $value) {
            if (isset($_SESSION['CSSLisible']['options'][$option])) {
                $this->set_option($option, $_SESSION['CSSLisible']['options'][$option]);
            }
        }
    }

    // On récupère les nouveaux réglages transmis via POST
    private function get_options_from_post() {

        if (isset($_POST['type_separateur']) && array_key_exists($_POST['type_separateur'], $this->listing_separateurs)) {
            $this->set_option('separateur', $this->listing_separateurs[$_POST['type_separateur']]);
        }

        if (isset($_POST['distance_selecteurs']) && ctype_digit($_POST['distance_selecteurs'])) {
            $this->set_option('distance_selecteurs', $_POST['distance_selecteurs']);
        }

        if (isset($_POST['type_indentation']) && ctype_digit($_POST['type_indentation'])) {
            $this->set_option('indentation', $_POST['type_indentation']);
        }
        $this->set_option('selecteurs_multiples_separes', isset($_POST['selecteurs_multiples_separes']));
        $this->set_option('supprimer_selecteurs_vides', isset($_POST['supprimer_selecteurs_vides']));
        $this->set_option('selecteur_par_ligne', isset($_POST['selecteur_par_ligne']));
        $this->set_option('tout_compresse', isset($_POST['tout_compresse']));

        if (isset($_POST['hex_colors_format']) && array_key_exists($_POST['hex_colors_format'],$this->listing_hex_colors_formats)) {
            $this->set_option('hex_colors_format', $_POST['hex_colors_format']);
        }
    }

    public function get_option($option) {
        return isset($this->options[$option]) ? $this->options[$option] : false;
    }

    private function set_option($option, $value) {
        $this->options[$option] = $value;
        $_SESSION['CSSLisible']['options'][$option] = $value;
    }

    public function short_hex_color_values($matches) {
        array_shift($matches);
        return implode($matches);
    }

	private function compress_css($css_to_compress,$lvl=0){
		
        $css_to_compress = strip_tags($css_to_compress);

        // Suppression des commentaires
        $css_to_compress = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css_to_compress);

        // Suppression des tabulations, espaces multiples, retours à la ligne, etc.
        $css_to_compress = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '	 ', '	 '), '', $css_to_compress);

        // Suppression des derniers espaces inutiles
        $css_to_compress = preg_replace('#([\s]*)([\{\}\:\;\(\)\,])([\s]*)#', '$2', $css_to_compress);

        // Ecriture trop lourde
        $css_to_compress = str_replace(';;', ';', $css_to_compress);
		$css_to_compress = preg_replace('#:0(px|em|ex|%|pt|pc|in|cm|mm|rem|vw|vh|vm);#', ':0;', $css_to_compress);
		
		// Simplification des codes couleurs hexadécimaux
		$css_to_compress = preg_replace_callback('#(:[^;]*\#)([a-fA-F\d])\2([a-fA-F\d])\3([a-fA-F\d])\4([^;]*;)#', array($this, 'short_hex_color_values'), $css_to_compress);
		
		
		if($lvl>0){
	        $css_to_compress = str_replace(';}', '}', $css_to_compress);
		}
		
		return $css_to_compress;
	}

	private function format_hex_color_values($matches) {
		switch($this->get_option('hex_colors_format')) {
			case 0:
				$formatted_color = $matches[2];
				break;
			case 1:
				$formatted_color = strtolower($matches[2]);
				break;
			case 2:
				$formatted_color = strtoupper($matches[2]);
				break;
		}

		return $matches[1] . $formatted_color . $matches[5];
	}

    private function clean_css($css_to_clean) {

		$css_to_clean = $this->compress_css($css_to_clean);

		// Formatage des codes couleur hexadécimaux
		$css_to_clean = preg_replace_callback('#(:[^;]*\#)((([a-fA-F\d]){3}){1,2})([^;]*;)#', array($this, 'format_hex_color_values'), $css_to_clean);
		
		// Supprime les sélecteurs vides
		if($this->get_option('supprimer_selecteurs_vides') || $this->get_option('tout_compresse')){
			$css_to_clean = preg_replace('#([^}]+){}#isU', '', $css_to_clean);
		}
		
        // == Mise en page améliorée ==
        // Début du listing des propriétés
        $css_to_clean = str_replace('{', ' {' . "\n", $css_to_clean);
        $css_to_clean = str_replace(';', ';' . "\n", $css_to_clean);
        $css_to_clean = str_replace("\n\n", "\n", $css_to_clean);

        // Fin du listing des propriétés
        $css_to_clean = str_replace('}', "\n" . '}' . "\n", $css_to_clean);

        // Fix url()
        $css_to_clean = preg_replace('#url\((.*)\)(\S)#', 'url($1) $2', $css_to_clean);
        $css_to_clean = preg_replace('#url\((.*)(\s)(.*)\)#', 'url($1$3)', $css_to_clean);
        $css_to_clean = str_replace(') ;', ');', $css_to_clean);

        return $css_to_clean;
    }

    private function mise_ecart_propriete($css_to_sort) {
        foreach ($this->strings_tofix as $type_tofix => $infos_tofix) {
            preg_match_all($infos_tofix['regex'], $css_to_sort, $matches);
            foreach ($matches[1] as $match) {
                $replace = '_||_' . $type_tofix . '_' . count($this->strings_tofix[$type_tofix]['list']) . '_||_';
                $css_to_sort = str_replace($match, $replace, $css_to_sort);
                $this->strings_tofix[$type_tofix]['list'][$replace] = $match;
            }
        }
        return $css_to_sort;
    }

    private function suppression_mise_ecart_propriete($css_to_sort) {
        foreach ($this->strings_tofix as $type_tofix => $infos_tofix) {
            foreach ($infos_tofix['list'] as $match => $replace) {
                $css_to_sort = str_replace($match, $replace, $css_to_sort);
            }
        }
        return $css_to_sort;
    }

    private function reindent_string($string, $trim=false) {

        $str_lines = explode("\n", $string);
        foreach ($str_lines as &$line) {
            $line = $this->listing_indentations[$this->get_option('indentation')][0] . $line;
        }

        $return_str = implode("\n", $str_lines);

        if ($trim) {
            $return_str = trim($return_str);
        }

        return $return_str;
    }

    private function reindent_media_queries($css_to_reindent) {

        // On récupère les media queries
        preg_match_all('#@media(.*){((.*)})([\s]+)}#isU', $css_to_reindent, $matches);

        foreach ($matches[2] as $match_media_query) {
	
			$tmp_match_media_query = $match_media_query;

            $matches_prop = array();
            $proprietes = array();

            // On met de côté le contenu des propriétés ( en les réindentant au passage )
            preg_match_all('#{([^{]*)}#isU', $tmp_match_media_query, $matches_prop);
            foreach ($matches_prop[1] as $i => $propriete) {
                $replace = '__||__propriete_' . $i . '__||__';
                $prop_to = '{' . $propriete . '}';
                $tmp_match_media_query = str_replace($prop_to, $replace, $tmp_match_media_query);
                $proprietes[$replace] = $prop_to;
            }

	        // On réindente le contenu de chaque media query
            $css_to_reindent = str_replace($match_media_query, $this->reindent_string($tmp_match_media_query), $css_to_reindent);

            // On remet les proprietes, en les reindentant
            foreach ($proprietes as $match => $replace) {
                $css_to_reindent = str_replace($match, $this->reindent_string($replace,1), $css_to_reindent);
            }

        }

        // On nettoie les espacements à la fin de chaque media query
        preg_match_all('#}([^{]*)}#', $css_to_reindent, $matches);
        foreach ($matches[0] as $match) {
            $css_to_reindent = str_replace($match, '}' . "\n" . '}', $css_to_reindent);
        }

        return $css_to_reindent;
    }

    // Tri des propriétés
    public function sort_css($css_to_sort) {

		$selecteur_par_ligne = ($this->get_option('selecteur_par_ligne') && !$this->get_option('tout_compresse'));

        $this->buffer_props = explode('}', $css_to_sort);
        $new_props = array();
        // On divise par propriétés
        foreach ($this->buffer_props as $prop) {
            $lines = explode("\n", $prop);
            $new_lines = array();
            $properties_tmp = array();
            $properties_dbl = array();
            // On divise par ligne

            foreach ($lines as $line) {
                $line_t = trim($line);
                $values = explode(':', $line_t);
                // C'est un selecteur, on l'ajoute à la suite.
                if (!isset($values[1]) || strpos($line_t, '{') !== FALSE) {
                    if (!empty($line_t)) {
                        $line_t_s = explode(',', $line_t);
                        $selecteur_glue = ',' . (!$selecteur_par_ligne && $this->get_option('selecteurs_multiples_separes') ? "\n" : ' ');
                        $new_lines[] = implode($selecteur_glue, $line_t_s);
                    }
                } else {
                    // On supprime les ; de fin de ligne
                    if (substr($values[1], -1) == ';')
                        $values[1] = substr($values[1], 0, -1);
                    // On met de côté la propriété
                    if (!isset($properties_tmp[$values[0]])) {
                        $properties_tmp[$values[0]] = $values[1];
                    } else {
                        if (!isset($properties_dbl[$values[0]]))
                            $properties_dbl[$values[0]] = array();
                        $properties_dbl[$values[0]][] = $values;
                    }
                }
            }

            // On trie les proprietes récupérées
            foreach ($this->listing_proprietes as $propriete) {
                if (isset($properties_tmp[$propriete])) {
                    $new_lines[] = $this->listing_indentations[$this->get_option('indentation')][0] . $propriete . $this->get_option('separateur') . $properties_tmp[$propriete] . ';';
                    unset($properties_tmp[$propriete]);
                }
                // On regarde aussi dans les doublons
                if (isset($properties_dbl[$propriete])) {
                    foreach ($properties_dbl[$propriete] as $values) {
                        $new_lines[] = $this->listing_indentations[$this->get_option('indentation')][0] . $values[0] . $this->get_option('separateur') . $values[1] . ';';
                    }
                    unset($properties_dbl[$propriete]);
                }
            }

            // On ajoute les proprietes qui n'ont pas été affichée pour l'instant
            foreach ($properties_tmp as $propriete => $valeur) {
                $new_lines[] = $this->listing_indentations[$this->get_option('indentation')][0] . $propriete . $this->get_option('separateur') . $valeur . ';';
                // On regarde aussi dans les doublons
                if (isset($properties_dbl[$propriete])) {
                    foreach ($properties_dbl[$propriete] as $values) {
                        $new_lines[] = $this->listing_indentations[$this->get_option('indentation')][0] . $values[0] . $this->get_option('separateur') . $values[1] . ';';
                    }
                    unset($properties_dbl[$propriete]);
                }
            }

            $new_props[] = implode((!$selecteur_par_ligne ? "\n":''), $new_lines);
        }

        $new_props = trim(
			implode(
				($selecteur_par_ligne ? "}\n":"\n". '}' . str_pad('', $this->get_option('distance_selecteurs') + 1, "\n")),
				 $new_props
			)
		);

        return $new_props;
    }

	// Ajout du commentaire en entête
	private function add_header($cleaned_css) {
		if (strlen($cleaned_css)) {
			$str_date = date('d/m/Y - H:i:s (U)');
			$indentation = $this->listing_indentations[$this->get_option('indentation')][0];

			$header = <<<EOT
/*
${indentation}Code reformaté le $str_date

${indentation}CSSLisible - http://github.com/Darklg/CSSLisible

${indentation}----------------------------------------------------
*/

EOT;
			$cleaned_css = $header . $cleaned_css;
		}
		
		return $cleaned_css;
	}
}
