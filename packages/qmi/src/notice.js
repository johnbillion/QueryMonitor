import React, { Component } from 'react';
import { NonTabular } from 'qmi';

export class Notice extends Component {

	render() {
		return (
			<NonTabular id={this.props.id}>
				<section>
					<div className="qm-notice">
						{this.props.children}
					</div>
				</section>
			</NonTabular>
		);
	}

}