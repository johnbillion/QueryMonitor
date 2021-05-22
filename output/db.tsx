import { Warning } from 'qmi';
import * as React from 'react';

import {
	__,
	sprintf,
} from '@wordpress/i18n';

interface dbItem {
	'server-version': string; // @TODO check
	'extension': string; // @TODO check
	'client-version': string; // @TODO check
	'user': string;
	'host': string;
	'database': string;
}

interface iDBProps {
	name: string;
	db: {
		info: dbItem;
		variables: {
			Variable_name: string;
			Value: string;
		}[];
	}
}

class DB extends React.Component<iDBProps, Record<string, unknown>> {

	render() {
		const {
			name,
			db,
		} = this.props;
		const info: dbItem = {
			'server-version': __( 'Server Version', 'query-monitor' ),
			'extension': __( 'Extension', 'query-monitor' ),
			'client-version': __( 'Client Version', 'query-monitor' ),
			'user': __( 'User', 'query-monitor' ),
			'host': __( 'Host', 'query-monitor' ),
			'database': __( 'Database', 'query-monitor' ),
		};

		return (
			<section>
				<h3>
					{ sprintf( __( 'Database: %s', 'query-monitor' ), name ) }
				</h3>
				<table>
					<tbody>
						{ Object.keys( info ).map( ( key: keyof typeof info ) => (
							<tr key={ key }>
								<th scope="row">
									{ info[key] }
								</th>
								<td>
									{ db.info[key] || (
										<span className="qm-warn">
											<Warning/>
											{ __( 'Unknown', 'query-monitor' ) }
										</span>
									) }
								</td>
							</tr>
						) ) }
						{ db.variables.map( variable => (
							<tr key={ variable.Variable_name }>
								<th scope="row">
									{ variable.Variable_name }
								</th>
								<td>
									{ variable.Value }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</section>
		);
	}

}

export default DB;
