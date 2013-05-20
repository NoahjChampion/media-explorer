<?php
/*
Copyright © 2013 Code for the People Ltd

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

*/

namespace EMM;

abstract class Template {

	abstract public function item( $id );

	abstract public function thumbnail( $id );

	abstract public function search( $id );

	abstract public function first_time( $id );

	final public function before_template( $id ) {
		?>
		<script type="text/html" id="tmpl-<?php echo esc_attr( $id ); ?>">
		<?php
	}

	final public function after_template( $id ) {
		?>
		</script>
		<?php
	}

}