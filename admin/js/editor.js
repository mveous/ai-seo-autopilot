/**
 * AI SEO Autopilot — block editor sidebar panel.
 *
 * Deliberately built with wp.element.createElement (no JSX/build step)
 * so the plugin ships and runs with zero bundler dependency, using only
 * WordPress core script handles as dependencies.
 */
( function ( wp ) {
	'use strict';

	var el              = wp.element.createElement;
	var useState         = wp.element.useState;
	var useEffect         = wp.element.useEffect;
	var registerPlugin   = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var TextControl       = wp.components.TextControl;
	var TextareaControl   = wp.components.TextareaControl;
	var ToggleControl     = wp.components.ToggleControl;
	var Button            = wp.components.Button;
	var Spinner           = wp.components.Spinner;
	var Notice            = wp.components.Notice;
	var useSelect         = wp.data.useSelect;
	var useEntityProp     = wp.coreData ? wp.coreData.useEntityProp : wp.editor.useEntityProp;
	var __                = wp.i18n.__;
	var apiFetch          = wp.apiFetch;

	var config = window.aiSeoAutopilotEditor || {};

	function ScoreBar( props ) {
		var score = props.score || 0;
		var color = score >= 80 ? '#16a34a' : score >= 50 ? '#d97706' : '#dc2626';

		return el(
			'div',
			{ style: { marginBottom: '12px' } },
			el(
				'div',
				{ style: { display: 'flex', justifyContent: 'space-between', marginBottom: '4px', fontSize: '12px' } },
				el( 'span', {}, __( 'SEO Score', 'ai-seo-autopilot' ) ),
				el( 'strong', {}, score )
			),
			el(
				'div',
				{ style: { background: '#e5e7eb', borderRadius: '999px', height: '6px', overflow: 'hidden' } },
				el( 'div', { style: { width: score + '%', background: color, height: '100%' } } )
			)
		);
	}

	function GenerateButton( props ) {
		var [ loading, setLoading ] = useState( false );
		var [ error, setError ] = useState( '' );

		function handleClick() {
			setLoading( true );
			setError( '' );

			apiFetch( {
				path: '/ai-seo/v1/ai/generate',
				method: 'POST',
				data: props.requestData,
			} )
				.then( function ( response ) {
					setLoading( false );
					props.onResult( response.data.result );
				} )
				.catch( function ( err ) {
					setLoading( false );
					setError( ( err && err.message ) || __( 'AI generation failed.', 'ai-seo-autopilot' ) );
				} );
		}

		return el(
			'div',
			{ style: { marginTop: '4px', marginBottom: '12px' } },
			el(
				Button,
				{ variant: 'secondary', onClick: handleClick, disabled: loading, isSmall: true },
				loading ? el( Spinner, {} ) : props.label
			),
			error && el( Notice, { status: 'error', isDismissible: false, style: { marginTop: '6px' } }, error )
		);
	}

	function AiSeoPanel() {
		var postId    = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostId();
		}, [] );
		var postType  = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		var [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
		var [ analysis, setAnalysis ] = useState( null );
		var [ analyzing, setAnalyzing ] = useState( false );

		var title       = meta ? meta[ config.metaPrefix + 'title' ] || '' : '';
		var description = meta ? meta[ config.metaPrefix + 'description' ] || '' : '';
		var keyword     = meta ? meta[ config.metaPrefix + 'focus_keyword' ] || '' : '';
		var canonical   = meta ? meta[ config.metaPrefix + 'canonical' ] || '' : '';
		var noindex     = meta ? !! meta[ config.metaPrefix + 'robots_noindex' ] : false;

		function updateMeta( key, value ) {
			var next = {};
			next[ config.metaPrefix + key ] = value;
			setMeta( Object.assign( {}, meta, next ) );
		}

		function runAnalysis() {
			if ( ! postId ) {
				return;
			}
			setAnalyzing( true );
			apiFetch( { path: '/ai-seo/v1/analyze', method: 'POST', data: { post_id: postId } } )
				.then( function ( response ) {
					setAnalyzing( false );
					setAnalysis( response.data );
				} )
				.catch( function () {
					setAnalyzing( false );
				} );
		}

		useEffect(
			function () {
				var timer = setTimeout( runAnalysis, 800 );
				return function () {
					clearTimeout( timer );
				};
			},
			[ postId, title, description ]
		);

		return el(
			PluginDocumentSettingPanel,
			{ name: 'ai-seo-autopilot-panel', title: __( 'AI SEO Autopilot', 'ai-seo-autopilot' ), className: 'ai-seo-editor-panel' },
			analysis && el( ScoreBar, { score: analysis.score } ),
			analyzing && el( 'p', { style: { fontSize: '12px', color: '#6b7280' } }, __( 'Analyzing…', 'ai-seo-autopilot' ) ),

			el( TextControl, {
				label: __( 'SEO Title', 'ai-seo-autopilot' ),
				value: title,
				maxLength: config.titleLimit,
				help: title.length + ' / ' + config.titleLimit,
				onChange: function ( value ) {
					updateMeta( 'title', value );
				},
			} ),
			el( GenerateButton, {
				label: __( 'Generate with AI', 'ai-seo-autopilot' ),
				requestData: { feature: 'title', post_id: postId },
				onResult: function ( result ) {
					updateMeta( 'title', result );
				},
			} ),

			el( TextareaControl, {
				label: __( 'Meta Description', 'ai-seo-autopilot' ),
				value: description,
				help: description.length + ' / ' + config.descriptionLimit,
				onChange: function ( value ) {
					updateMeta( 'description', value );
				},
			} ),
			el( GenerateButton, {
				label: __( 'Generate with AI', 'ai-seo-autopilot' ),
				requestData: { feature: 'meta_description', post_id: postId },
				onResult: function ( result ) {
					updateMeta( 'description', result );
				},
			} ),

			el( TextControl, {
				label: __( 'Focus Keyword', 'ai-seo-autopilot' ),
				value: keyword,
				onChange: function ( value ) {
					updateMeta( 'focus_keyword', value );
				},
			} ),

			el( TextControl, {
				label: __( 'Canonical URL', 'ai-seo-autopilot' ),
				value: canonical,
				placeholder: 'https://',
				onChange: function ( value ) {
					updateMeta( 'canonical', value );
				},
			} ),

			el( ToggleControl, {
				label: __( 'Discourage search engines (noindex)', 'ai-seo-autopilot' ),
				checked: noindex,
				onChange: function ( value ) {
					updateMeta( 'robots_noindex', value );
				},
			} ),

			analysis && analysis.checks && analysis.checks.length > 0 && el(
				'ul',
				{ style: { listStyle: 'none', margin: '12px 0 0', padding: 0, fontSize: '12px' } },
				analysis.checks.map( function ( check, index ) {
					var color = check.status === 'good' ? '#16a34a' : check.status === 'bad' ? '#dc2626' : '#d97706';
					return el(
						'li',
						{ key: index, style: { marginBottom: '4px', color: color } },
						( check.status === 'good' ? '✓ ' : '• ' ) + check.message
					);
				} )
			)
		);
	}

	registerPlugin( 'ai-seo-autopilot', {
		render: AiSeoPanel,
		icon: 'superhero-alt',
	} );
} )( window.wp );
