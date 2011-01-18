<?php

	/**
	 * LICENSE: Copyright (c) 2010 François 'cahnory' Germain
	 * Permission is hereby granted, free of charge, to any person obtaining a copy
	 * of this software and associated documentation files (the "Software"), to deal
	 * in the Software without restriction, including without limitation the rights
	 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	 * copies of the Software, and to permit persons to whom the Software is
	 * furnished to do so, subject to the following conditions:
	 * 
	 * The above copyright notice and this permission notice shall be included in
	 * all copies or substantial portions of the Software.
	 * 
	 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	 * THE SOFTWARE.
	 * that is available through the world-wide-web at the following URI:
	 * http://www.php.net/license/3_01.txt.  If you did not receive a copy of
	 * the PHP License and are unable to obtain it through the web, please
	 * send a note to license@php.net so we can mail you a copy immediately.
	 *
	 * @author     François 'cahnory' Germain <cahnory@gmail.com>
	 * @copyright  2010 François Germain
	 * @license    http://www.opensource.org/licenses/mit-license.php
	 */
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
			'functionOpening'	=>	'\$\((?:.(?![\s]*{))*(?=\)[\s]*\{)',
			'functionSource'	=>	'\)[\s]*\{(?:.(?!\$\}))*.(?=\$\})',
			'functionCall'		=>	'(?<=\$)[^;:\s({}]+(?:\((?=\);)|\((?:.(?!\);))*.(?=\);))',
			'nodeOpening'		=>	'(?<=;|\{|\}|^)[^;(){}]+(?=\{)',
			'property'			=>	'(?<=;|\{|\}|^)[^{}:;]+(?=:)',
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
			$this->_parent	=	$this->_root;
		}
		
		public	function	__toString()
		{
			$string	=	'';
			if($this->_selector && $this->_properties) {
				$string	.=	self::_getBlockString($this->_selector, $this->toStyle());
			}
			return	$string.$this->getChildrenString();
		}
		
		static	private	function	_getBlockString($selector, $properties)
		{
			return	$selector."{\r\n".$properties.'}'."\r\n";
		}
		
		private	function	_parseString($string)
		{
			//	Remove comments and extra white spaces
			$string	=	preg_replace('#/\*\*/|/\*(.(?!\*/))*.\*/#s', ' ', $string);
			$string	=	preg_replace('#[\s]+#s', ' ', $string);
			$string	=	preg_replace('#(?<=[:;{]|^)[\s]*|[\s]*(?=[:;}])|;(?=})#s', '', $string);
			
			//	Explode the string (openNode, closeNode, property, value,...)
			$pattern	=	'#('.implode(')|(', self::$_parsingPatterns).')#s';
			preg_match_all($pattern, $string, $match);
			
			$lastMapKey	=	sizeof(self::$_parsingMap) - 1;
			for($i = 0; isset($match[0][$i]); $i++) {
				for($j = $lastMapKey; isset(self::$_parsingMap[$j]); $j--) {				
					if(!empty($match[$j+1][$i]) || $match[$j+1][$i] === '0') {
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
					array_pad(explode(';', $this->_parseVariables($args)), sizeof($this->_calledFunction['arguments']), NULL)
				);
				extract($args);
				eval($this->_calledFunction['source']);
			}
			$this->_calledFunction	=	NULL;
		}
		
		private	function	_parseFunctionOpening($string)
		{			
			if($this->_allowUserFunc && !$this->_parsingFunction && $this-> _parsingVariable) {
				$string	=	preg_replace('#\$\(|[\s]*#s', '', $string);
				$this->_parsingFunction	=	array(
					'arguments'	=>	explode(',', $string)
				);
			}
		}
		
		private	function	_parseFunctionSource($string)
		{
			if($this->_parsingFunction && $this-> _parsingVariable) {
				$this->_parsingFunction['source']			=	preg_split('#^\)[\s]*\{#s', $string);
				$this->_parsingFunction['source']			=	str_replace('$->','$this->_parsingNode->', end($this->_parsingFunction['source']));
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
			if(preg_match('#^[\s]*\$(.+)#s', $string, $m)) {
				$this-> _parsingVariable	=	$m[1];
			} elseif($this->_parsingNode) {
				$this->_parsingProperty	=	$string;
			}
		}
		
		private	function	_parseValue($string)
		{				
			if($this->_parsingProperty !== NULL && $this->_parsingNode) {
				if($lock = preg_match('#^\+#s', $this->_parsingProperty))
					$this->_parsingProperty	=	preg_replace('#^\+#s', '', $this->_parsingProperty);					
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
				'#\$(?!->)([^;:\s{}]+)(?=[;:\s{}]|$)#s',
				array($this, '_parseVariablesCallback'),
				$string
			);
			return	$string;
		}
		
		private	function _parseVariablesCallback($m)
		{
			return	isset($this->_root->_variables[$m[1]]) ? $this->_root->_variables[$m[1]] : NULL;
		}
		
		public	function	addChild($selectors)
		{
			$selectors		=	explode(',', $this->_parseVariables($selectors));
			$set			=	new AcCSSCollection;
			$set->_parent	=	$this;
			foreach($selectors as $selector) {
				$child				=	new AcCSS($this->_root);
				$child->_parent		=	$this;
				$child->_selector	=	trim($selector);
				
				//	Suffix or child
				if(preg_match('#^\$->#s', $child->_selector)) {
					$child->_selector	=	preg_replace('#^\$->#s', '', $child->_selector);
				} else {
					$child->_selector	=	' '.$child->_selector;
				}
				$child->_selector	=	trim($this->_selector.$child->_selector);
				$this->_children[]	=	$child;
				$set->addNode($child);
			}
			return	$set;
		}
		
		public	function	addString($string)
		{
			$this->_parseString($string);
		}
		
		public	function	allowUserFunc($allow)
		{
			$this->_root->_allowUserFunc	=	$allow;
		}
		
		public	function	get($name)
		{
			return	isset($this->_properties[$name]) ? end($this->_properties[$name]) : NULL;
		}
		
		public	function	getChildrenString()
		{
			$string		=	'';
			$children	=	'';
			$lastString	=	NULL;
			$selectors	=	array();
			foreach($this->_children as $child) {
				$childString	=	$child->toStyle();
				$children		.=	$child->getChildrenString();
				if(empty($childString))	continue;
				
				if($childString != $lastString) {
					if($lastString !== NULL) {
						$string	.=	self::_getBlockString(implode(',', $selectors), $lastString);
						$selectors	=	array();
					}
					$lastString		=	$childString;
				}
				$selectors[]	=	$child->_selector;
			}
			if($lastString) {
				$string	.=	self::_getBlockString(implode(',', $selectors), $lastString);
			}
			return	$string.$children;
		}
		
		public	function	push($name, $add)
		{
			$value	=	NULL;
			if(!isset($this->_properties[$name])) {
				$this->_properties[$name]	=	array();
			} elseif(!$lock) {
				foreach($this->_properties[$name] as $k => $v) {
					if(!$v['locked']) {
						$value	=	$v['value'];
						unset($this->_properties[$name][$k]);
					}
				}
			}
			$add	=	$this->_parseVariables($add);
			if($value && $add)
				$value	.=	',';
			$this->_properties[$name][]	=	array(
				'value'		=>	$value.$add,
				'locked'	=>	false
			);
		}
		
		public	function	set($name, $value, $lock = false)
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
				'value'		=>	$this->_parseVariables($value),
				'locked'	=>	$lock
			);
		}
		
		public	function	toStyle()
		{
			$string	=	'';
			foreach($this->_properties as $name => $values) {
				foreach($values as $value) {
					$string	.=	'    '.$name.':'.$value['value'].";\r\n";
				}
			}
			return	$string;
		}
	}

?>