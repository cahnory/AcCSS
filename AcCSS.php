<?php

	class AcCSS
	{
		private	$_root;
		private	$_parent;
		private	$_selector;
		private	$_children		=	array();
		private	$_properties	=	array();
		private	$_functions		=	array();
		private	$_variables		=	array();
		private	$_allowUserFunc	=	false;
		
		private		$_calledFunction;
		private		$_parsingFunction;
		private		$_parsingProperty;
		private		$_parsingVariable;
		private		$_parsingNode;
		
		static	private	$_parsingPatterns	=	array(
			'functionOpening'	=>	'(?<=\$\()(?:.(?![\s]*{))*(?=\)[\s]*\{)',
			'functionSource'	=>	'\)[\s]*\{(?:.(?!\}))*(?=\$\})',
			'functionCall'		=>	'(?<=\$)[^;:\s({}]+\((?:.(?!\);))*.(?=\);)',
			'nodeOpening'		=>	'[^\s;(){}][^;(){}]+(?=\{)',
			'property'			=>	'(?<=;|\{|\}|^)[^{}:]+(?=:)',
			'value'				=>	'(?<=:)[^{};]+(?=;|\}|$)',
			'closure'			=>	'\}'
		);
		
		static	private	$_parsingMap	=	array(
			'_parseFunctionOpening',
			'_parseFunctionSource',
			'_parseFunctionCall',
			'_parseNodeOpening',
			'_parseProperty',
			'_parseValue',
			'_parseClosure'
		);
		
		public	function	__construct(AcCSS $root = NULL)
		{
			$this->_root	=	$root === NULL ? $this : $root;
		}
		
		public	function	__toString()
		{
			$string	=	'';
			if($this->_selector && $this->_properties) {
				$string	.=	$this->_selector.'{';
				foreach($this->_properties as $name => $values) {
					foreach($values as $value) {
						$string	.=	$name.':'.$value['value'].';';
					}
				}
				$string	.=	'}'."\r\n";
			}
			foreach($this->_children as $child) {
				$string	.=	$child;
			}
			return	$string;
		}
		
		private	function	_parseString($string)
		{
			//	Remove comments and extra white spaces
			$string	=	preg_replace('#/\*\*/|/\*(.(?!\*/))*.\*/#', ' ', $string);
			$string	=	preg_replace('#[\s]+#s', ' ', $string);
			$string	=	preg_replace('#(?<=[:;{]|^)[\s]*|[\s]*(?=[:;}])|;(?=})#', '', $string);
			
			//	Explode the string (openNode, closeNode, property, value,...)
			$pattern	=	'#('.implode(')|(', self::$_parsingPatterns).')#si';
			preg_match_all($pattern, $string, $match);
			preg_match('#function[\s]*\(#si', $string, $m);
			
			$lastMapKey	=	sizeof(self::$_parsingMap) - 1;
			for($i = 0; isset($match[0][$i]); $i++) {
				for($j = $lastMapKey; isset(self::$_parsingMap); $j--) {
					if(!empty($match[$j+1][$i])) {
						call_user_func(array($this, self::$_parsingMap[$j]), $match[$j+1][$i]);
						break;
					}
				}
			}
		}
		
		private	function	_parseClosure()
		{
			if($this->_parsingNode) {
				$this->_parsingNode			=	$this->_parsingNode->_parent;						
				$this-> _parsingVariable	=	NULL;
				$this->_parsingProperty		=	NULL;
			}
		}
		
		private	function	_parseFunctionCall($string)
		{
			list($name, $args)	=	explode('(',$string,2);
			if(isset($this->_functions[$name])) {
				$this->_calledFunction	=	$this->_functions[$name];
				$args	=	array_combine(
					$this->_calledFunction['arguments'],
					array_pad(explode(';', $args), sizeof($this->_calledFunction['arguments']), NULL)
				);
				extract($args);
				eval($this->_calledFunction['source']);
			}
			$this->_calledFunction	=	NULL;
		}
		
		private	function	_parseFunctionOpening($string)
		{
			if($this->_allowUserFunc && !$this->_parsingFunction && $this-> _parsingVariable) {
				$string	=	preg_replace('#[\s]*#s', '', $string);
				$this->_parsingFunction	=	array(
					'arguments'	=>	explode(',', $string)
				);
			}
		}
		
		private	function	_parseFunctionSource($string)
		{
			if($this->_parsingFunction && $this-> _parsingVariable) {
				$this->_parsingFunction['source']			=	str_replace('$->','$this->_parsingNode->', end(preg_split('#^\)[\s]*\{#',$string)));
				$this->_functions[$this->_parsingVariable]	=	$this->_parsingFunction;
				$this->_parsingFunction		=	NULL;
				$this-> _parsingVariable	=	NULL;
			}
		}
		
		private	function	_parseNodeOpening($string)
		{
			if(!$this->_parsingNode) {
				$this->_parsingNode	=	$this;
			}
			$this->_parsingNode	=	$this->_parsingNode->addChild($string);
		}
		
		private	function	_parseProperty($string)
		{
			if(preg_match('#^[\s]*\$(.+)#', $string, $m)) {
				$this-> _parsingVariable	=	$m[1];
			} elseif($this->_parsingNode) {
				$this->_parsingProperty	=	$string;
			}
		}
		
		private	function	_parseValue($string)
		{				
			if($this->_parsingProperty !== NULL && $this->_parsingNode) {
				if($lock = preg_match('#^\+#', $this->_parsingProperty))
					$this->_parsingProperty	=	preg_replace('#^\+#', '', $this->_parsingProperty);					
				$this->_parsingNode->set($this->_parsingProperty, $string, $lock);
			} elseif($this-> _parsingVariable !== NULL) {
				$this-> _variables[$this-> _parsingVariable]	=	$this->_parseVariables($string);
			}
			$this-> _parsingVariable	=	NULL;
			$this->_parsingProperty		=	NULL;
		}
		
		private	function	_parseVariables($string)
		{
			$string	=	preg_replace_callback(
				'#\$([^;:\s{}-]+(?=[;:\s{}]))#',
				array($this, '_parseVariablesCallback'),
				$string
			);
			return	$string;
		}
		
		private	function _parseVariablesCallback($m)
		{
			return	isset($this-> _variables[$m[1]]) ? $this-> _variables[$m[1]] : NULL;
		}
		
		public	function	addChild($selectors)
		{
			$selectors		=	explode(',', $selectors);
			$set			=	new AcCSSCollection;
			$set->_parent	=	$this;
			foreach($selectors as $selector) {
				$child				=	new AcCSS($this->_root);
				$child->_parent		=	$this;
				$child->_selector	=	trim($this->_selector.' '.trim($selector));
				$this->_children[]	=	$child;
				$set->add($child);
			}
			return	$set;
		}
		
		public	function	addString($string)
		{
			$this->_parseString($string);
		}
		
		public	function	allowUserFunc($allow) {
			$this->_allowUserFunc	=	$allow;
		}
		
		public	function	set($name, $value, $lock)
		{
			if(!isset($this->_properties[$name])) {
				$this->_properties[$name]	=	array();
			} elseif(!$lock) {
				foreach($this->_properties[$name] as $k => $v) {
					if(!$v['locked']) {
						unset($this->_properties[$name][$k]);
					}
				}
			}
			$this->_properties[$name][]	=	array(
				'value'		=>	$this->_root->_parseVariables($value),
				'locked'	=>	$lock
			);
		}
	}

?>