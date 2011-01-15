<?php
	
	class AcCSSCollection
	{
		public	$_parent;
		private	$_nodes	=	array();
		
		public	function	__toString()
		{
			$string	=	'';
			foreach($this->_nodes as $node) {
				$string	.=	$node;
			}
			return	$string;
		}
		
		public	function	add(AcCSS $node)
		{
			$this->_nodes[spl_object_hash($node)]	=	$node;
		}
		
		public	function	addChild($selectors)
		{
			$set	=	new AcCSSCollection;
			$set->_parent	=	$this;
			foreach($this->_nodes as $node) {
				$set->merge($node->addChild($selectors));
			}
			return	$set;
		}
		
		public	function	addString($string)
		{
			foreach($this->_nodes as $node) {
				$node->addString($string);
			}
		}
		
		public	function	merge(AcCSSCollection $set)
		{
			foreach($set->_nodes as $node) {
				$this->_nodes[spl_object_hash($node)]	=	$node;
			}
		}
		
		public	function	set($name, $value, $lock)
		{
			foreach($this->_nodes as $node) {
				$node->set($name, $value, $lock);
			}
		}
	}

?>